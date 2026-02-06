<?php

namespace App\Commands;

use App\Client\Requests\UpdateDatabaseClusterRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Support\UpdateFields;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class DatabaseClusterUpdate extends BaseCommand
{
    protected $signature = 'database-cluster:update
                            {database? : The database ID or name}
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
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Database Cluster');

        $database = $this->resolvers()->databaseCluster()->from($this->argument('database'));

        $types = spin(
            fn () => $this->client->databaseClusters()->types(),
            'Fetching database types...',
        );

        $type = collect($types)->firstWhere('type', $database->type);

        $fields = $this->getFieldDefinitions($database, $type);

        $data = [];

        foreach ($fields as $optionName => $field) {
            if ($this->option($optionName)) {
                $value = $this->option($optionName);
                $data[$field['key']] = $this->coerceConfigValue($field['key'], $value, $type);

                $this->reportChange(
                    $field['label'],
                    (string) $field['current'],
                    (string) $value,
                );
            }
        }

        $updatedDatabase = $this->resolveUpdatedDatabase($database, $type, $fields, $data);

        $this->outputJsonIfWanted($updatedDatabase);

        success('Database cluster updated');

        outro("Database cluster updated: {$updatedDatabase->name}");
    }

    protected function resolveUpdatedDatabase(DatabaseCluster $database, DatabaseType $type, array $fields, array $data): DatabaseCluster
    {
        if (! $this->isInteractive()) {
            if (empty($data)) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                exit(self::FAILURE);
            }

            return $this->updateDatabase($database, $data);
        }

        if (empty($data)) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($fields, $database, $type),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            exit(self::FAILURE);
        }

        return $this->updateDatabase($database, $data);
    }

    protected function updateDatabase(DatabaseCluster $database, array $configUpdates): DatabaseCluster
    {
        $config = array_merge($database->config, $configUpdates);

        spin(
            fn () => $this->client->databaseClusters()->update(new UpdateDatabaseClusterRequestData(
                clusterId: $database->id,
                config: $config,
            )),
            'Updating database cluster...',
        );

        return $this->client->databaseClusters()->get($database->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the database cluster?');
    }

    protected function getFieldDefinitions(DatabaseCluster $database, DatabaseType $type): array
    {
        $fields = new UpdateFields;

        $config = $database->config;

        foreach ($type->configSchema as $schemaField) {
            $schema = is_array($schemaField) ? $schemaField : $schemaField->toArray();
            $name = $schema['name'];
            $optionName = str_replace('_', '-', $name);
            $current = $config[$name] ?? $schema['example'] ?? null;
            $promptSchema = $schema;
            $promptCurrent = $current;

            $fields->add($optionName, fn ($value) => $this->promptForConfigValue($promptSchema, $value ?? $promptCurrent))
                ->currentValue($current)
                ->dataKey($name)
                ->label(str_replace('_', ' ', ucfirst($name)));
        }

        return $fields->get();
    }

    protected function collectDataAndUpdate(array $fields, DatabaseCluster $database, DatabaseType $type): DatabaseCluster
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($fields)->mapWithKeys(fn ($field, $key) => [
                $key => $field['label'],
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            exit(self::FAILURE);
        }

        $data = [];

        foreach ($selection as $optionName) {
            $field = $fields[$optionName];
            $newValue = ($field['prompt'])($field['current']);
            $data[$field['key']] = $this->coerceConfigValue($field['key'], $newValue, $type);
        }

        return $this->updateDatabase($database, $data);
    }

    protected function promptForConfigValue(array $schema, mixed $current): mixed
    {
        $name = $schema['name'];
        $fieldType = $schema['type'] ?? 'string';
        $label = str_replace('_', ' ', ucfirst($name));
        $hint = $schema['description'] ?? null;
        $enum = $schema['enum'] ?? [];
        $min = $schema['min'] ?? null;
        $max = $schema['max'] ?? null;

        if (count($enum) > 0) {
            $options = is_array($enum) ? array_combine($enum, $enum) : $enum;

            return select(
                label: $label,
                options: $options,
                default: (string) $current,
                required: true,
            );
        }

        if ($fieldType === 'boolean') {
            return confirm(
                label: $label,
                default: (bool) $current,
                hint: $hint,
            );
        }

        if ($fieldType === 'integer') {
            return (int) number(
                label: $label,
                default: (string) ($current ?? 0),
                required: true,
                hint: $hint,
                min: $min,
                max: $max,
            );
        }

        if ($fieldType === 'number') {
            return (float) number(
                label: $label,
                default: (string) ($current ?? 0),
                required: true,
                hint: $hint,
                min: $min,
                max: $max,
            );
        }

        return text(
            label: $label,
            default: $current !== null ? (string) $current : '',
            required: true,
            hint: $hint,
        );
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
