<?php

namespace App\Commands;

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Dto\Cache;
use App\Dto\Database;
use App\Dto\Environment;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class EnvironmentUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:update
                            {environment? : The environment ID or name}
                            {--branch= : Git branch}
                            {--build-command= : Build command}
                            {--deploy-command= : Deploy command}
                            {--database-id= : Database ID to attach (empty string to detach)}
                            {--cache-id= : Cache ID to attach (empty string to detach)}
                            {--websocket-application-id= : WebSocket application ID to attach (empty string to detach)}
                            {--force : Force update without confirmation}';

    protected $description = 'Update an environment';

    protected $aliases = ['env:update'];

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $this->defineFields($environment);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedEnvironment = $this->runUpdate(
            fn () => $this->updateEnvironment($environment),
            fn () => $this->collectDataAndUpdate($environment),
        );

        $this->outputJsonIfWanted($updatedEnvironment);

        success("Environment updated: {$updatedEnvironment->name}");
    }

    protected function updateEnvironment(Environment $environment): Environment
    {
        spin(
            fn () => $this->client->environments()->update(
                new UpdateEnvironmentRequestData(
                    environmentId: $environment->id,
                    branch: $this->form()->get('branch'),
                    buildCommand: $this->form()->get('build_command'),
                    deployCommand: $this->form()->get('deploy_command'),
                    databaseSchemaId: $this->form()->get('database_id'),
                    cacheId: $this->form()->get('cache_id'),
                    websocketApplicationId: $this->form()->get('websocket_application_id'),
                ),
            ),
            'Updating environment...',
        );

        return $this->client->environments()->get($environment->id);
    }

    protected function defineFields(Environment $environment): void
    {
        $this->form()->define(
            'branch',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Branch',
                    default: $value ?? $environment->branch ?? '',
                ),
            ),
        )->setPreviousValue($environment->branch ?? '');

        $this->form()->define(
            'build_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Build command',
                    default: $value ?? $environment->buildCommand ?? '',
                ),
            ),
            'build-command',
        )->setPreviousValue($environment->buildCommand ?? '');

        $this->form()->define(
            'deploy_command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Deploy command',
                    default: $value ?? $environment->deployCommand ?? '',
                ),
            ),
            'deploy-command',
        )->setPreviousValue($environment->deployCommand ?? '');

        $this->form()->define(
            'database_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectDatabase($value, $environment->databaseSchemaId),
            ),
            'database-id',
        )->setPreviousValue($environment->databaseSchemaId ?? '');

        $this->form()->define(
            'cache_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectCache($value, $environment->cacheId),
            ),
            'cache-id',
        )->setPreviousValue($environment->cacheId ?? '');

        $this->form()->define(
            'websocket_application_id',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->selectWebsocketApplication($value, $environment->websocketApplicationId),
            ),
            'websocket-application-id',
        )->setPreviousValue($environment->websocketApplicationId ?? '');
    }

    protected function selectDatabase(?string $value, ?string $currentId): string
    {
        $clusters = spin(
            fn () => $this->client->databaseClusters()->include('schemas')->list()->collect(),
            'Fetching databases...',
        );

        $clustersWithSchemas = $clusters->filter(fn ($cluster) => ! empty($cluster->schemas))->values();

        if ($clustersWithSchemas->isEmpty()) {
            $this->failAndExit('No databases found. Create one with `cloud database-cluster:create` and `cloud database:create`.');
        }

        $currentCluster = $clustersWithSchemas->first(
            fn ($cluster) => collect($cluster->schemas)->contains(fn (Database $schema) => $schema->id === $currentId),
        );

        if ($clustersWithSchemas->count() === 1) {
            $selectedCluster = $clustersWithSchemas->first();
        } else {
            $clusterOptions = $currentId ? ['' => '— Detach —'] : [];

            foreach ($clustersWithSchemas as $cluster) {
                $clusterOptions[$cluster->id] = $cluster->name;
            }

            $selectedClusterId = (string) select(
                label: 'Database cluster',
                options: $clusterOptions,
                default: $currentCluster !== null ? $currentCluster->id : '',
            );

            if ($selectedClusterId === '') {
                return '';
            }

            $selectedCluster = $clustersWithSchemas->firstWhere('id', $selectedClusterId);
        }

        $options = $currentId ? ['' => '— Detach —'] : [];

        foreach ($selectedCluster->schemas as $schema) {
            /** @var Database $schema */
            $options[$schema->id] = $schema->id === $currentId ? "{$schema->name} (current)" : $schema->name;
        }

        return (string) select(
            label: 'Database to attach',
            options: $options,
            default: $value ?? ($selectedCluster->id === $currentCluster?->id ? $currentId : null) ?? '',
        );
    }

    protected function selectCache(?string $value, ?string $currentId): string
    {
        $caches = spin(
            fn () => $this->client->caches()->list()->collect(),
            'Fetching caches...',
        );

        if ($caches->isEmpty()) {
            $this->failAndExit('No caches found. Create one with `cloud cache:create`.');
        }

        $options = $currentId ? ['' => '— Detach —'] : [];

        foreach ($caches as $cache) {
            /** @var Cache $cache */
            $options[$cache->id] = $cache->id === $currentId ? "{$cache->name} (current)" : $cache->name;
        }

        return (string) select(
            label: 'Cache to attach',
            options: $options,
            default: $value ?? $currentId ?? '',
        );
    }

    protected function selectWebsocketApplication(?string $value, ?string $currentId): string
    {
        $clusters = spin(
            fn () => $this->client->websocketClusters()->list()->collect(),
            'Fetching WebSocket clusters...',
        );

        if ($clusters->isEmpty()) {
            $this->failAndExit('No WebSocket clusters found. Create one with `cloud websocket-cluster:create` and `cloud websocket-application:create`.');
        }

        $currentCluster = $clusters->first(
            fn (WebsocketCluster $cluster) => in_array($currentId, $cluster->applicationIds, true),
        );

        if ($clusters->count() === 1) {
            $selectedCluster = $clusters->first();
        } else {
            $clusterOptions = $currentId ? ['' => '— Detach —'] : [];

            foreach ($clusters as $cluster) {
                /** @var WebsocketCluster $cluster */
                $clusterOptions[$cluster->id] = $cluster->name;
            }

            $selectedClusterId = (string) select(
                label: 'WebSocket cluster',
                options: $clusterOptions,
                default: $currentCluster !== null ? $currentCluster->id : '',
            );

            if ($selectedClusterId === '') {
                return '';
            }

            $selectedCluster = $clusters->firstWhere('id', $selectedClusterId);
        }

        $apps = spin(
            fn () => $this->client->websocketApplications()->list($selectedCluster->id)->collect(),
            'Fetching WebSocket applications...',
        );

        if ($apps->isEmpty()) {
            $this->failAndExit('No WebSocket applications in that cluster. Create one with `cloud websocket-application:create`.');
        }

        $options = $currentId ? ['' => '— Detach —'] : [];

        foreach ($apps as $app) {
            /** @var WebsocketApplication $app */
            $options[$app->id] = $app->id === $currentId ? "{$app->name} (current)" : $app->name;
        }

        return (string) select(
            label: 'WebSocket application to attach',
            options: $options,
            default: $value ?? ($selectedCluster->id === $currentCluster?->id ? $currentId : null) ?? '',
        );
    }

    protected function collectDataAndUpdate(Environment $environment): Environment
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateEnvironment($environment);
    }
}
