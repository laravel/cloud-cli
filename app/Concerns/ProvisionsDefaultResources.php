<?php

namespace App\Concerns;

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Dto\DatabasePreset;
use App\Dto\DatabaseType;
use App\Dto\Environment;
use App\Enums\DatabaseClusterPreset;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

/**
 * Provisioning with sensible defaults, for commands that create an application and get it
 * running in one go. Expects the using command to also use CreatesDatabase,
 * CreatesDatabaseCluster, CreatesWebSocketApplication and CreatesWebSocketCluster, and to
 * define `--database` and `--database-preset` options.
 */
trait ProvisionsDefaultResources
{
    protected ?string $appName = null;

    protected ?string $region = null;

    /**
     * @var array{0: DatabaseType, 1: string, 2: DatabasePreset}|null
     */
    protected ?array $validatedDatabase = null;

    protected function attachToEnvironment(Environment $environment, ?string $databaseSchemaId, ?string $websocketApplicationId): void
    {
        if ($databaseSchemaId === null && $websocketApplicationId === null) {
            return;
        }

        $this->loopUntilValid(
            function ($errors) use ($environment, $databaseSchemaId, $websocketApplicationId) {
                if ($errors->messageContains('database', 'please wait')) {
                    info('Waiting a few seconds...');
                    Sleep::for(CarbonInterval::seconds(5));
                }

                return spin(fn () => $this->client->environments()->update(
                    new UpdateEnvironmentRequestData(
                        environmentId: $environment->id,
                        databaseSchemaId: $databaseSchemaId,
                        websocketApplicationId: $websocketApplicationId,
                    ),
                ), 'Updating environment...');
            },
            handleNonInteractiveErrors: function ($errors) {
                if ($errors->messageContains('database', 'please wait')) {
                    Sleep::for(CarbonInterval::seconds(5));

                    return true;
                }

                return false;
            },
        );
    }

    protected function waitForUrlToBeReady(Environment $environment, int $timeoutSeconds = 120): bool
    {
        $deadline = now()->addSeconds($timeoutSeconds);

        do {
            try {
                $response = Http::timeout(5)->get($environment->url);

                // A 404 means the domain isn't routing to the app yet. Any other status is a
                // real answer from a running app, including a 401 or 403 from one that keeps
                // its root path behind auth.
                if (! $response->notFound()) {
                    return ! $response->serverError();
                }
            } catch (ConnectionException) {
                // The domain isn't resolving yet.
            }

            Sleep::for(CarbonInterval::seconds(2));
        } while (now()->lessThan($deadline));

        return false;
    }

    /**
     * Resolve the `--database` option into a type and, when the option names one, a version.
     *
     * A null version means "the newest the API offers for that type", so `postgres`
     * keeps tracking new releases while `postgres17` stays pinned.
     *
     * @return array{0: string, 1: string|null}|null
     */
    protected function resolveDatabaseType(): ?array
    {
        $input = strtolower((string) $this->option('database'));

        if ($input === '') {
            return [DatabaseClusterPreset::NeonServerlessPostgres->value, null];
        }

        if ($input === 'postgres') {
            return [DatabaseClusterPreset::NeonServerlessPostgres->value, null];
        }

        if ($input === 'mysql') {
            return [DatabaseClusterPreset::LaravelMysql->value, null];
        }

        if (preg_match('/^postgres(\d+)$/', $input, $matches)) {
            return [DatabaseClusterPreset::NeonServerlessPostgres->value, $matches[1]];
        }

        if (DatabaseClusterPreset::tryFrom($input) !== null) {
            return [$input, null];
        }

        if (($legacy = DatabaseClusterPreset::fromLegacyType($input)) !== null) {
            return [$legacy[0]->value, $legacy[1]];
        }

        $validValues = implode(', ', ['postgres', 'postgres<version> (e.g. postgres18)', 'mysql', ...array_map(fn (DatabaseClusterPreset $e) => $e->value, DatabaseClusterPreset::cases())]);

        $this->outputErrorOrThrow('Invalid --database value "'.$input.'". Must be one of: '.$validValues);

        return null;
    }

    protected function resolveDatabasePreset(string $type, string $region): DatabasePreset
    {
        $presets = collect($this->client->databaseClusters()->presets($type, $region));
        $input = $this->option('database-preset');

        if ($presets->isEmpty()) {
            $this->outputErrorOrThrow('No presets are available for database type "'.$type.'" in region "'.$region.'".');
        }

        $preset = $presets->first(fn (DatabasePreset $preset) => strcasecmp($preset->key, $input ?? 'dev') === 0);

        // Plans and regions can leave out the dev preset, so only an explicit choice must match.
        if ($preset === null && $input === null) {
            return $presets->first();
        }

        if ($preset === null) {
            $this->outputErrorOrThrow(sprintf(
                'Invalid --database-preset value "%s" for database type "%s" in region "%s". Must be one of: %s (case-insensitive)',
                $input,
                $type,
                $region,
                $presets->pluck('key')->implode(', '),
            ));
        }

        return $preset;
    }

    /**
     * Check the database options before anything is created, so a bad flag fails fast instead
     * of leaving half-provisioned resources behind.
     */
    protected function validateDatabaseOptions(string $region): void
    {
        [$type, $version] = $this->resolveDatabaseTypeAndVersion();

        $this->validatedDatabase = [$type, $version, $this->resolveDatabasePreset($type->type, $region)];
    }

    /**
     * @return array{0: DatabaseType, 1: string}
     */
    protected function resolveDatabaseTypeAndVersion(): array
    {
        $types = $this->client->databaseClusters()->types();
        $types = collect($types)->filter(fn (DatabaseType $type) => DatabaseClusterPreset::tryFrom($type->type) !== null)->values();

        [$resolvedType, $resolvedVersion] = $this->resolveDatabaseType();

        $type = $types->firstWhere('type', $resolvedType);

        if ($type === null) {
            $this->outputErrorOrThrow('Database type "'.$resolvedType.'" is not available from the API.');
        }

        $version = $resolvedVersion ?? $type->latestVersion();

        if ($version === null || ! in_array($version, $type->versions, true)) {
            $this->outputErrorOrThrow(sprintf(
                'Version "%s" is not available for database type "%s". Available versions: %s',
                $version ?? 'unknown',
                $type->type,
                implode(', ', $type->versions) ?: 'none',
            ));
        }

        return [$type, $version];
    }

    protected function provisionDatabaseOpinionated(): ?string
    {
        [$type, $version, $preset] = $this->validatedDatabase ?? [...$this->resolveDatabaseTypeAndVersion(), null];

        $defaults = $this->databaseClusterDefaults();
        $name = $defaults['name'] ?? 'database';
        $region = $defaults['region'] ?? 'us-east-2';

        $clusters = $this->client->databaseClusters()->list()->collect();
        $cluster = $clusters->firstWhere('name', $name);
        $databaseName = $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : 'main';

        if (! $cluster) {
            $preset ??= $this->resolveDatabasePreset($type->type, $region);
            $cluster = $this->createDatabaseClusterWithOptions($type->type, $version, $preset, $name, $region);
            $cluster = $this->client->databaseClusters()->include('databases')->get($cluster->id);
        }

        return $this->loopUntilValid(
            fn () => $this->createDatabaseWithName($cluster, $databaseName)->id,
            handleNonInteractiveErrors: function ($errors) {
                if ($errors->messageContains('database', 'please wait')) {
                    Sleep::for(CarbonInterval::seconds(5));

                    return true;
                }

                return false;
            },
        );
    }

    protected function provisionWebsocketOpinionated(): ?string
    {
        $defaults = $this->getWebSocketClusterDefaults();

        $clusters = $this->client->websocketClusters()->list()->collect();

        $cluster = $clusters->isEmpty()
            ? $this->createWebSocketClusterWithOptions($defaults)
            : $clusters->first();

        $appName = $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : 'websocket';

        $websocketApp = $this->createWebSocketApplicationWithOptions($cluster, array_merge($defaults, ['name' => $appName]));

        return $websocketApp->id;
    }

    protected function getWebSocketClusterDefaults(): array
    {
        return array_filter([
            'name' => $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : null,
            'region' => $this->region,
        ]);
    }

    protected function databaseClusterDefaults(): array
    {
        return array_filter([
            'name' => $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : null,
            'region' => $this->region,
        ]);
    }
}
