<?php

namespace App\Concerns;

use App\Client\Requests\CreateDatabaseClusterRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Dto\Region;
use App\Enums\DatabaseClusterPreset;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesDatabaseCluster
{
    protected ?string $databaseClusterPreset = null;

    protected function createDatabaseCluster(array $defaults = []): DatabaseCluster
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Name',
                    default: $value ?? $defaults['name'] ?? '',
                    required: true,
                    validate: fn ($v) => match (true) {
                        ! preg_match('/^[a-z0-9_-]+$/', $v) => 'Must contain only lowercase letters, numbers, hyphens and underscores',
                        strlen($v) < 3 => 'Must be at least 3 characters',
                        strlen($v) > 40 => 'Must be less than 40 characters',
                        default => null,
                    },
                ),
            ),
        );

        $types = spin(
            fn () => $this->client->databaseClusters()->types(),
            'Fetching database types...',
        );

        $types = collect($types)->filter(fn (DatabaseType $type) => DatabaseClusterPreset::tryFrom($type->type) !== null)->values();

        $this->form()->prompt(
            'type',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Database type',
                    options: $types->mapWithKeys(fn (DatabaseType $type) => [$type->type => $type->label])->toArray(),
                    default: $value ?? $defaults['type'] ?? null,
                    required: true,
                ),
            ),
        );

        $selectedType = $types->firstWhere('type', $this->form()->get('type'));

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $regionOptions = collect($regions)->filter(
            fn (Region $region) => in_array($region->value, $selectedType->regions),
        );

        $defaultRegion = $defaults['region'] ?? null;

        if ($defaultRegion !== null && ! in_array($defaultRegion, $selectedType->regions)) {
            $defaultRegion = null;
        }

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => select(
                    label: 'Region',
                    options: $regionOptions->mapWithKeys(fn (Region $region) => [
                        $region->value => $region->label,
                    ])->toArray(),
                    default: $value ?? $defaultRegion ?? $regionOptions->first()?->value,
                    required: true,
                ),
            ),
        );

        $config = $this->databaseClusterConfigFromPreset($selectedType) ?? $this->promptForDatabaseClusterConfig($selectedType);

        return spin(
            fn () => $this->client->databaseClusters()->create(
                new CreateDatabaseClusterRequestData(
                    type: $this->form()->get('type'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    config: $config,
                ),
            ),
            'Creating database cluster...',
        );
    }

    protected function databaseClusterConfigFromPreset(DatabaseType $type): ?array
    {

        $clusterPreset = DatabaseClusterPreset::from($type->type);
        $presets = $clusterPreset->presets();
        $presets['Custom'] = [];

        if ($this->databaseClusterPreset) {
            return $this->databaseClusterPreset === 'Custom' ? null : $presets[$this->databaseClusterPreset];
        }

        $this->databaseClusterPreset = selectWithContext(
            label: 'Configuration',
            options: collect($presets)->mapWithKeys(fn ($preset, $key) => [
                $key => count($preset) > 0 ? [
                    $key,
                    $clusterPreset->description()($preset),
                ] : [$key, $key.' configuration'],
            ])->toArray(),
            default: array_key_first($presets),
            required: true,
        );

        if ($this->databaseClusterPreset !== 'Custom') {
            return $presets[$this->databaseClusterPreset];
        }

        return null;
    }

    protected function promptForDatabaseClusterConfig(DatabaseType $type): array
    {
        $config = [];

        foreach ($type->configSchema as $field) {
            $schema = is_array($field) ? $field : $field->toArray();
            $name = $schema['name'];
            $fieldType = $schema['type'] ?? 'string';
            $required = $schema['required'] ?? false;
            $description = $schema['description'] ?? null;
            $min = $schema['min'] ?? null;
            $max = $schema['max'] ?? null;
            $enum = $schema['enum'] ?? [];
            $example = $schema['example'] ?? null;
            $key = 'config.'.$name;

            $label = match ($name) {
                'cu_min' => 'CU min',
                'cu_max' => 'CU max',
                default => str_replace('_', ' ', ucfirst($name)),
            };

            if (count($enum) > 0) {
                $options = is_array($enum) ? array_combine($enum, $enum) : $enum;
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => select(
                            label: $label,
                            options: $options,
                            default: $value ?? $example ?? array_key_first($options),
                            required: $required,
                        ),
                    ),
                );
            } elseif ($fieldType === 'boolean') {
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => confirm(
                            label: $label,
                            default: filter_var($value ?? $example, FILTER_VALIDATE_BOOLEAN),
                            hint: $description,
                        ),
                    ),
                );
            } elseif ($fieldType === 'integer') {
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => number(
                            label: $label,
                            default: $value ?? $example ?? 0,
                            required: $required,
                            hint: $description,
                            min: $min,
                            max: $max,
                        ),
                    ),
                );
            } else {
                $this->form()->prompt(
                    $key,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => text(
                            label: $label,
                            default: $value ?? $example ?? '',
                            required: $required,
                            hint: $description,
                        ),
                    ),
                );
            }
        }

        return collect($this->form()->filled())
            ->filter(fn ($value) => str_starts_with($value->key, 'config.'))
            ->mapWithKeys(fn ($value) => [
                str_replace('config.', '', $value->key) => $value->value(),
            ])->toArray();
    }

    protected function createDatabaseClusterWithOptions(string $type, string $preset, string $name, string $region): DatabaseCluster
    {
        $enum = DatabaseClusterPreset::tryFrom($type);

        if ($enum === null) {
            throw new RuntimeException(
                'Invalid database type. Must be one of: '.implode(', ', array_map(fn (DatabaseClusterPreset $e) => $e->value, DatabaseClusterPreset::cases())),
            );
        }

        $presets = $enum->presets();

        if (! array_key_exists($preset, $presets)) {
            throw new RuntimeException(
                'Invalid database preset. Must be one of: '.implode(', ', array_keys($presets)),
            );
        }

        $config = $presets[$preset];

        return spin(
            fn () => $this->client->databaseClusters()->create(
                new CreateDatabaseClusterRequestData(
                    type: $type,
                    name: $name,
                    region: $region,
                    config: $config,
                ),
            ),
            'Creating database cluster...',
        );
    }
}
