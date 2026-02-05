<?php

namespace App\Actions;

use App\Client\Connector;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Dto\Region;
use App\Enums\DatabaseClusterPreset;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CreateDatabaseCluster
{
    public function run(Connector $client, array $defaults = []): DatabaseCluster
    {
        $name = text(
            label: 'Database cluster name',
            default: $defaults['name'] ?? '',
            required: true,
            validate: fn ($value) => match (true) {
                ! preg_match('/^[a-z0-9_-]+$/', $value) => 'Must contain only lowercase letters, numbers, hyphens and underscores',
                strlen($value) < 3 => 'Must be at least 3 characters',
                strlen($value) > 40 => 'Must be less than 40 characters',
                default => null,
            },
        );

        $types = spin(
            fn () => $client->databaseClusters()->types(),
            'Fetching database types...',
        );

        $types = collect($types)->filter(fn (DatabaseType $type) => DatabaseClusterPreset::tryFrom($type->type) !== null)->values();

        $selectedTypeValue = select(
            label: 'Database type',
            options: $types->mapWithKeys(fn (DatabaseType $type) => [$type->type => $type->label])->toArray(),
            default: $defaults['type'] ?? null,
            required: true,
        );

        $selectedType = collect($types)->firstWhere('type', $selectedTypeValue);

        $regions = spin(
            fn () => $client->meta()->regions(),
            'Fetching regions...',
        );

        $regionOptions = collect($regions)->filter(
            fn (Region $region) => in_array($region->value, $selectedType->regions),
        );

        $defaultRegion = $defaults['region'] ?? null;

        if ($defaultRegion !== null && ! in_array($defaultRegion, $selectedType->regions)) {
            $defaultRegion = null;
        }

        $region = select(
            label: 'Region',
            options: $regionOptions->mapWithKeys(fn (Region $region) => [
                $region->value => $region->label,
            ])->toArray(),
            default: $defaultRegion ?? $regionOptions->first()?->value,
            required: true,
        );

        $config = $this->fromConfigPreset($selectedType) ?? $this->promptForConfig($selectedType);

        return spin(
            fn () => $client->databaseClusters()->create(
                $selectedTypeValue,
                $name,
                $region,
                $config,
            ),
            'Creating database cluster...',
        );
    }

    protected function fromConfigPreset(DatabaseType $type): ?array
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

    protected function promptForConfig(DatabaseType $type): array
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
