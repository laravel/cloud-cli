<?php

namespace App\Commands;

use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\Validates;
use App\Dto\DatabaseType;
use App\Dto\Region;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class DatabaseClusterCreate extends BaseCommand
{
    use DeterminesDefaultRegion;
    use Validates;

    protected $signature = 'database-cluster:create
                            {--name= : Database cluster name}
                            {--type= : Database type}
                            {--region= : Database region}
                            {--json : Output as JSON}';

    protected $description = 'Create a new database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Database Cluster');

        $database = $this->loopUntilValid($this->createDatabaseCluster(...));

        $this->outputJsonIfWanted($database);

        success('Database cluster created');

        outro("Database cluster created: {$database->name}");
    }

    protected function createDatabaseCluster()
    {
        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Database cluster name',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($value) => match (true) {
                        ! preg_match('/^[a-z0-9_-]+$/', $value) => 'Must contain only lowercase letters, numbers, hyphens and underscores',
                        strlen($value) < 3 => 'Must be at least 3 characters',
                        strlen($value) > 40 => 'Must be less than 40 characters',
                        default => null,
                    },
                ),
            ),
        );

        $types = spin(
            fn () => $this->client->databaseClusters()->types(),
            'Fetching database types...',
        );

        $this->addParam(
            'type',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Database type',
                    options: collect($types)->mapWithKeys(fn (DatabaseType $type) => [$type->type => $type->label])->toArray(),
                    default: $value,
                    required: true,
                ))
                ->nonInteractively(fn () => null),
        );

        $selectedType = collect($types)->firstWhere('type', $this->getParam('type'));

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $regionOptions = collect($regions)->filter(
            fn (Region $region) => in_array($region->value, $selectedType->regions),
        );

        $this->addParam(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Region',
                    options: $regionOptions->mapWithKeys(fn (Region $region) => [
                        $region->value => $region->label,
                    ])->toArray(),
                    default: $value ?? (in_array($this->getDefaultRegion(), $selectedType->regions) ? $this->getDefaultRegion() : null),
                    required: true,
                ))
                ->nonInteractively(fn () => in_array($this->getDefaultRegion(), $selectedType->regions) ? $this->getDefaultRegion() : $regionOptions->first()?->value),
        );

        $config = $this->fromConfigPreset($selectedType) ?? $this->promptForConfig($selectedType);

        return spin(
            fn () => $this->client->databaseClusters()->create(
                $this->getParam('type'),
                $this->getParam('name'),
                $this->getParam('region'),
                $config,
            ),
            'Creating database cluster...',
        );
    }

    protected function formatNumber(string $number)
    {
        return match ($number) {
            '0.25' => '¼',
            '0.5' => '½',
            default => $number,
        };
    }

    protected function fromConfigPreset(DatabaseType $type): ?array
    {
        $presets = [
            'laravel_mysql_8' => [
                'Dev' => [
                    'size' => 'db-flex.m-1vcpu-512mb',
                    'storage' => 5,
                    'retention_days' => 1,
                    'uses_scheduled_snapshots' => false,
                ],
                'Prod' => [
                    'size' => 'db-flex.m-1vcpu-2gb',
                    'storage' => 20,
                    'retention_days' => 7,
                    'uses_scheduled_snapshots' => false,
                ],
                'Scale' => [
                    'size' => 'db-pro.m-4vcpu-16gb',
                    'storage' => 200,
                    'retention_days' => 14,
                    'uses_scheduled_snapshots' => false,
                ],
            ],
            'neon_serverless_postgres_18' => [
                'Dev' => [
                    'cu_min' => 0.25,
                    'cu_max' => 0.25,
                    'suspend_seconds' => 300,
                    'retention_days' => 0,
                ],
                'Prod' => [
                    'cu_min' => 0.25,
                    'cu_max' => 1,
                    'suspend_seconds' => 0,
                    'retention_days' => 7,
                ],
                'Scale' => [
                    'cu_min' => 1,
                    'cu_max' => 4,
                    'suspend_seconds' => 0,
                    'retention_days' => 14,
                ],
            ],
            'neon_serverless_postgres_17' => [
                'Dev' => [
                    'cu_min' => 0.25,
                    'cu_max' => 0.25,
                    'suspend_seconds' => 300,
                    'retention_days' => 0,
                ],
                'Prod' => [
                    'cu_min' => 0.25,
                    'cu_max' => 1,
                    'suspend_seconds' => 0,
                    'retention_days' => 7,
                ],
                'Scale' => [
                    'cu_min' => 1,
                    'cu_max' => 4,
                    'suspend_seconds' => 0,
                    'retention_days' => 14,
                ],
            ],
        ];

        $typePreset = $presets[$type->type] ?? null;

        $contextCreators = [
            'laravel_mysql_8' => fn ($preset) => sprintf(
                '%s · %sGB storage · %d %s backups',
                str($preset['size'])
                    ->replaceMatches(
                        '/^db-(pro|flex)\.(m|c|g)-(\d+)vcpu-(\d+)(gb|mb)$/',
                        '$1 ($3 vCPU · $4 $5 RAM)',
                    )
                    ->replace('gb', 'GiB')
                    ->replace('mb', 'MiB')
                    ->ucfirst()
                    ->toString(),
                $preset['storage'],
                $preset['retention_days'],
                Str::plural('day', $preset['retention_days']),
            ),
            'neon_serverless_postgres_18' => fn ($preset) => sprintf(
                '%s vCPU units · %s · %s',
                $preset['cu_min'] === $preset['cu_max'] ? $this->formatNumber($preset['cu_min']) : $this->formatNumber($preset['cu_min']).' – '.$this->formatNumber($preset['cu_max']),
                $preset['suspend_seconds'] > 0 ? 'Hibernate after '.$preset['suspend_seconds'].' seconds' : 'No hibernation',
                $preset['retention_days'] === 0 ? 'No backups' : $preset['retention_days'].' days PITR',
            ),
            'neon_serverless_postgres_17' => fn ($preset) => sprintf(
                '%s vCPU units · %s · %s',
                $preset['cu_min'] === $preset['cu_max'] ? $this->formatNumber($preset['cu_min']) : $this->formatNumber($preset['cu_min']).' – '.$this->formatNumber($preset['cu_max']),
                $preset['suspend_seconds'] === 0 ? 'Hibernate after '.$preset['suspend_seconds'].' seconds' : 'No hibernation',
                $preset['retention_days'] === 0 ? 'No backups' : $preset['retention_days'].' days PITR',
            ),
        ];

        if (! $typePreset) {
            return null;
        }

        $typePreset['Custom'] = [];

        $selectedPreset = selectWithContext(
            label: 'Configuration',
            options: collect($typePreset)->mapWithKeys(fn ($preset, $key) => [
                $key => count($preset) > 0 ? [
                    $key,
                    $contextCreators[$type->type]($preset),
                ] : [$key, $key.' configuration'],
            ])->toArray(),
            default: array_key_first($typePreset),
            required: true,
        );

        if ($selectedPreset !== 'Custom') {
            return $typePreset[$selectedPreset];
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
