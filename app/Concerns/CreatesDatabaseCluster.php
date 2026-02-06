<?php

namespace App\Concerns;

use App\Client\Requests\CreateDatabaseClusterRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Dto\Region;
use App\Enums\DatabaseClusterPreset;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesDatabaseCluster
{
    protected function createDatabaseCluster(array $defaults = []): DatabaseCluster
    {
        $this->fields()->add(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Database cluster name',
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

        $this->fields()->add(
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

        $selectedType = $types->firstWhere('type', $this->fields()->get('type'));

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

        $this->fields()->add(
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
            fn () => $this->client->databaseClusters()->create(new CreateDatabaseClusterRequestData(
                type: $this->fields()->get('type'),
                name: $this->fields()->get('name'),
                region: $this->fields()->get('region'),
                clusterConfig: $config,
            )),
            'Creating database cluster...',
        );
    }

    protected function databaseClusterConfigFromPreset(DatabaseType $type): ?array
    {
        $clusterPreset = DatabaseClusterPreset::from($type->type);
        $presets = $clusterPreset->presets();
        $presets['Custom'] = [];

        $selectedPreset = selectWithContext(
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

        if ($selectedPreset !== 'Custom') {
            return $presets[$selectedPreset];
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

            $label = str_replace('_', ' ', ucfirst($name));
            $hint = $description;

            if (count($enum) > 0) {
                $options = is_array($enum) ? array_combine($enum, $enum) : $enum;
                $config[$name] = select(
                    label: $label,
                    options: $options,
                    default: $example ?? array_key_first($options),
                    required: $required,
                );
            } elseif ($fieldType === 'boolean') {
                $config[$name] = confirm(
                    label: $label,
                    default: filter_var($example, FILTER_VALIDATE_BOOLEAN),
                    hint: $hint,
                );
            } elseif ($fieldType === 'integer') {
                $config[$name] = number(
                    label: $label,
                    default: $example ?? 0,
                    required: $required,
                    hint: $hint,
                    min: $min,
                    max: $max,
                );
            } else {
                $config[$name] = text(
                    label: $label,
                    default: $example !== null ? (string) $example : '',
                    required: $required,
                    hint: $hint,
                );
            }
        }

        return $config;
    }
}
