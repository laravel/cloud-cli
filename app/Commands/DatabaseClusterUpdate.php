<?php

namespace App\Commands;

use App\Client\Requests\UpdateDatabaseClusterRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class DatabaseClusterUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    protected $signature = 'database-cluster:update
                            {cluster? : The cluster ID or name}
                            {--size= : Instance size}
                            {--storage= : Storage in GB}
                            {--retention-days= : Days to retain backups}
                            {--is-public= : Whether publicly accessible}
                            {--uses-scheduled-snapshots= : Whether scheduled backups are enabled}
                            {--cu-min= : Minimum compute units (Neon)}
                            {--cu-max= : Maximum compute units (Neon)}
                            {--suspend-seconds= : Seconds before hibernation (Neon)}
                            {--uses-pitr= : Whether point-in-time recovery is enabled}
                            {--maintenance-window= : UTC maintenance window}
                            {--deployment-option= : single-az or multi-az}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Database Cluster');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $types = spin(
            fn () => $this->client->databaseClusters()->types(),
            'Fetching database types...',
        );

        $type = collect($types)->firstWhere('type', $cluster->type);

        $this->defineFields($cluster, $type);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedDatabase = $this->runUpdate(
            fn () => $this->updateCluster($cluster, $type),
            fn () => $this->collectDataAndUpdate($cluster, $type),
        );

        $this->outputJsonIfWanted($updatedDatabase);

        success("Database cluster updated: {$updatedDatabase->name}");
    }

    protected function updateCluster(DatabaseCluster $cluster, DatabaseType $type): DatabaseCluster
    {
        $config = $cluster->config;

        foreach ($type->configSchema as $schemaField) {
            $schema = is_array($schemaField) ? $schemaField : $schemaField->toArray();
            $name = $schema['name'];
            $value = $this->form()->get($name);

            if ($value !== null) {
                $config[$name] = $this->coerceConfigValue($name, $value, $type);
            }
        }

        spin(
            fn () => $this->client->databaseClusters()->update(
                new UpdateDatabaseClusterRequestData(
                    clusterId: $cluster->id,
                    config: collect($config)
                        ->filter(fn ($value, $key) => str_starts_with($key, 'config.'))
                        ->mapWithKeys(fn ($value, $key) => [
                            str_replace('config.', '', $key) => $value,
                        ])
                        ->toArray(),
                ),
            ),
            'Updating database cluster...',
        );

        return $this->client->databaseClusters()->get($cluster->id);
    }

    protected function defineFields(DatabaseCluster $cluster, DatabaseType $type): void
    {
        foreach ($type->configSchema as $field) {
            $schema = is_array($field) ? $field : $field->toArray();
            $name = 'config.'.$schema['name'];
            $optionName = str_replace('_', '-', $name);
            $current = $cluster->config[$name] ?? $schema['example'] ?? null;
            $fieldType = $schema['type'] ?? 'string';
            $description = $schema['description'] ?? null;
            $min = $schema['min'] ?? null;
            $max = $schema['max'] ?? null;
            $enum = $schema['enum'] ?? [];

            $label = match ($name) {
                'cu_min' => 'CU min',
                'cu_max' => 'CU max',
                default => str_replace('_', ' ', ucfirst($name)),
            };

            if (count($enum) > 0) {
                $options = is_array($enum) ? array_combine($enum, $enum) : $enum;

                $this->form()->define(
                    $name,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => select(
                            label: $label,
                            options: $options,
                            default: (string) ($value ?? $current ?? array_key_first($options)),
                            required: true,
                        ),
                    ),
                    $optionName,
                )->setPreviousValue($current !== null ? (string) $current : '');
            } elseif ($fieldType === 'boolean') {
                $this->form()->define(
                    $name,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => confirm(
                            label: $label,
                            default: filter_var($value ?? $current, FILTER_VALIDATE_BOOLEAN),
                            hint: $description,
                        ),
                    ),
                    $optionName,
                )->setPreviousValue($current !== null ? ($current ? 'true' : 'false') : '');
            } elseif ($fieldType === 'integer') {
                $this->form()->define(
                    $name,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => (int) number(
                            label: $label,
                            default: (string) ($value ?? $current ?? 0),
                            required: true,
                            hint: $description,
                            min: $min,
                            max: $max,
                        ),
                    ),
                    $optionName,
                )->setPreviousValue($current !== null ? (string) $current : '');
            } elseif ($fieldType === 'number') {
                $this->form()->define(
                    $name,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => (float) number(
                            label: $label,
                            default: (string) ($value ?? $current ?? 0),
                            required: true,
                            hint: $description,
                            min: $min,
                            max: $max,
                        ),
                    ),
                    $optionName,
                )->setPreviousValue($current !== null ? (string) $current : '');
            } else {
                $this->form()->define(
                    $name,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => text(
                            label: $label,
                            default: $value ?? $current ?? '',
                            required: true,
                            hint: $description,
                        ),
                    ),
                    $optionName,
                )->setPreviousValue($current !== null ? (string) $current : '');
            }
        }
    }

    protected function collectDataAndUpdate(DatabaseCluster $database, DatabaseType $type): DatabaseCluster
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

        return $this->updateCluster($database, $type);
    }

    protected function coerceConfigValue(string $key, mixed $value, DatabaseType $type): mixed
    {
        $schemaField = collect($type->configSchema)->firstWhere('name', $key);

        if (! $schemaField) {
            return $value;
        }

        $schema = is_array($schemaField) ? $schemaField : $schemaField->toArray();
        $fieldType = $schema['type'] ?? 'string';

        return match ($fieldType) {
            'integer' => (int) $value,
            'number' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
