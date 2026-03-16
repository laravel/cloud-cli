<?php

namespace App\Commands\Concerns;

use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Enums\DatabaseClusterPreset;
use Carbon\CarbonInterval;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

trait ProvisionsDatabases
{
    protected function resolveDatabaseType(): ?string
    {
        $aliases = [
            'postgres' => DatabaseClusterPreset::NeonServerlessPostgres18->value,
            'postgres18' => DatabaseClusterPreset::NeonServerlessPostgres18->value,
            'postgres17' => DatabaseClusterPreset::NeonServerlessPostgres17->value,
            'mysql' => DatabaseClusterPreset::LaravelMysql8->value,
        ];

        $input = $this->option('database');

        if ($input === null || $input === '') {
            return DatabaseClusterPreset::NeonServerlessPostgres18->value;
        }

        if (isset($aliases[strtolower($input)])) {
            return $aliases[strtolower($input)];
        }

        if (DatabaseClusterPreset::tryFrom($input) !== null) {
            return $input;
        }

        $validValues = implode(', ', [...array_keys($aliases), ...array_map(fn (DatabaseClusterPreset $e) => $e->value, DatabaseClusterPreset::cases())]);

        $this->outputErrorOrThrow('Invalid --database value "'.$input.'". Must be one of: '.$validValues);

        return null;
    }

    protected function resolveDatabasePreset(string $type): string
    {
        $validPresets = ['Dev', 'Prod', 'Scale'];
        $input = $this->option('database-preset') ?? 'Dev';
        $normalized = ucfirst(strtolower($input));

        if (! in_array($normalized, $validPresets)) {
            $this->outputErrorOrThrow('Invalid --database-preset value "'.$input.'". Must be one of: '.implode(', ', $validPresets).' (case-insensitive)');
        }

        $preset = DatabaseClusterPreset::from($type);

        if (! array_key_exists($normalized, $preset->presets())) {
            $this->outputErrorOrThrow('Preset "'.$normalized.'" is not available for database type "'.$type.'".');
        }

        return $normalized;
    }

    protected function provisionDatabaseOpinionated(): ?string
    {
        $types = $this->client->databaseClusters()->types();
        $types = collect($types)->filter(fn (DatabaseType $type) => DatabaseClusterPreset::tryFrom($type->type) !== null)->values();

        $resolvedType = $this->resolveDatabaseType();

        $type = $types->firstWhere('type', $resolvedType);

        if ($type === null) {
            if ($resolvedType === DatabaseClusterPreset::NeonServerlessPostgres18->value) {
                $type = $types->firstWhere('type', DatabaseClusterPreset::NeonServerlessPostgres17->value);
            }

            if ($type === null) {
                $this->outputErrorOrThrow('Database type "'.$resolvedType.'" is not available from the API.');
            }
        }

        $preset = $this->resolveDatabasePreset($type->type);
        $defaults = $this->databaseClusterDefaults();
        $name = $defaults['name'] ?? 'database';
        $region = $defaults['region'] ?? 'us-east-2';

        $clusters = $this->client->databaseClusters()->list()->collect();
        $cluster = $clusters->firstWhere('name', $name);
        $databaseName = $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : 'main';

        if (! $cluster) {
            $cluster = $this->createDatabaseClusterWithOptions($type->type, $preset, $name, $region);
            $cluster = $this->client->databaseClusters()->include('schemas')->get($cluster->id);
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

    protected function getDatabase(DatabaseCluster $database): ?Database
    {
        $options = collect($database->schemas)->mapWithKeys(fn (Database $schema) => [$schema->id => $schema->name]);
        $options->prepend('Create new database', 'new');

        $schema = select(
            label: 'Database',
            options: $options->toArray(),
            default: $database->schemas[0]->id ?? null,
            required: true,
        );

        if ($schema !== 'new') {
            return collect($database->schemas)->firstWhere('id', $schema);
        }

        return $this->loopUntilValid(
            fn () => $this->createDatabase($database),
        );
    }

    protected function getDatabaseCluster(): ?DatabaseCluster
    {
        $databasesPaginator = $this->client->databaseClusters()->include('schemas')->list();
        $databases = $databasesPaginator->collect();

        if ($databases->isEmpty()) {
            warning('No databases found.');

            $createDatabase = confirm('Do you want to create a new database?');

            if ($createDatabase) {
                return $this->loopUntilValid(
                    fn () => $this->createDatabaseCluster($this->databaseClusterDefaults()),
                );
            }

            return null;
        }

        $options = $databases->collect()->mapWithKeys(fn (DatabaseCluster $database) => [$database->id => $database->name]);
        $options->prepend('Create new database cluster', 'new');

        $database = select(
            label: 'Database cluster',
            options: $options->toArray(),
            default: $databases->first()->id ?? null,
            required: true,
        );

        if ($database !== 'new') {
            return $databases->firstWhere('id', $database);
        }

        return $this->loopUntilValid(
            fn () => $this->createDatabaseCluster($this->databaseClusterDefaults()),
        );
    }

    protected function databaseClusterDefaults(): array
    {
        return array_filter([
            'name' => $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : null,
            'region' => $this->region,
        ]);
    }
}
