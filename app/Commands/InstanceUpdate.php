<?php

namespace App\Commands;

use App\Client\Requests\UpdateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use App\Exceptions\CommandExitException;
use JsonException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\textarea;

class InstanceUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'instance:update
                            {instance? : The instance ID or name}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}
                            {--scaling-type= : Scaling type}
                            {--uses-scheduler= : Uses scheduler}
                            {--scaling-cpu-threshold-percentage= : Scaling CPU threshold percentage}
                            {--scaling-memory-threshold-percentage= : Scaling memory threshold percentage}
                            {--uses-octane= : Uses Octane}
                            {--uses-inertia-ssr= : Uses Inertia SSR}
                            {--scale-to-zero= : Uses scale to zero}
                            {--scale-to-zero-timeout= : Minutes without requests before scaling to zero (enables scale to zero)}
                            {--sleep-with-app= : Scale worker replicas to zero alongside the app (worker instances only)}
                            {--probes= : Health checks as JSON keyed by startup, readiness, liveness; replaces all existing checks, null removes them (app instances only)}
                            {--hibernation= : Deprecated alias for --scale-to-zero}
                            {--hibernation-timeout= : Deprecated alias for --scale-to-zero-timeout}
                            {--force : Force update without confirmation}';

    protected $description = 'Update an instance';

    /**
     * @var array<string, string>
     */
    protected array $deprecatedOptions = [
        'hibernation' => 'scale-to-zero',
        'hibernation-timeout' => 'scale-to-zero-timeout',
    ];

    public function options()
    {
        $options = parent::options();

        foreach ($this->deprecatedOptions as $deprecated => $current) {
            if (($options[$deprecated] ?? null) !== null && ($options[$current] ?? null) === null) {
                $options[$current] = $options[$deprecated];
            }
        }

        return $options;
    }

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Instance');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $this->defineFields($instance);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedInstance = $this->runUpdate(
            fn () => $this->updateInstance($instance),
            fn () => $this->collectDataAndUpdate($instance),
        );

        $this->outputJsonIfWanted($updatedInstance);

        success('Instance updated');
    }

    protected function updateInstance(EnvironmentInstance $instance): EnvironmentInstance
    {
        $hibernationTimeout = $this->resolveHibernationTimeout($instance);
        $probes = $this->resolveProbes();

        spin(
            fn () => $this->client->instances()->update(
                new UpdateInstanceRequestData(
                    instanceId: $instance->id,
                    size: $this->form()->get('size'),
                    minReplicas: $this->form()->integer('min_replicas'),
                    maxReplicas: $this->form()->integer('max_replicas'),
                    scalingType: $this->form()->get('scaling_type'),
                    usesScheduler: $this->form()->boolean('uses_scheduler'),
                    scalingCpuThresholdPercentage: $this->form()->get('scaling_cpu_threshold_percentage'),
                    scalingMemoryThresholdPercentage: $this->form()->get('scaling_memory_threshold_percentage'),
                    usesOctane: $this->form()->boolean('uses_octane'),
                    usesInertiaSsr: $this->form()->boolean('uses_inertia_ssr'),
                    hibernationTimeout: $hibernationTimeout === false ? null : $hibernationTimeout,
                    disableHibernation: $hibernationTimeout === false,
                    sleepWithApp: $this->form()->boolean('sleep_with_app'),
                    probes: $probes === false ? null : $probes,
                    removeProbes: $probes === false,
                ),
            ),
            'Updating instance...',
        );

        return $this->client->instances()->get($instance->id);
    }

    protected function defineFields(EnvironmentInstance $instance): void
    {
        $this->form()->define(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Size',
                    options: collect($this->client->instances()->sizes()->all())
                        ->mapWithKeys(fn ($size) => [$size->name => $size->description])
                        ->toArray(),
                    required: true,
                    default: $value ?? $instance->size,
                ),
            ),
        )->setPreviousValue($instance->size);

        $this->form()->define(
            'min_replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => (int) number(
                    label: 'Minimum replicas',
                    default: (string) ($value ?? $instance->minReplicas),
                    min: 1,
                    max: 10,
                ),
            ),
            'min-replicas',
        )->setPreviousValue((string) $instance->minReplicas);

        $this->form()->define(
            'max_replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => (int) number(
                    label: 'Maximum replicas',
                    default: (string) ($value ?? $instance->maxReplicas),
                    min: 1,
                    max: 10,
                ),
            ),
            'max-replicas',
        )->setPreviousValue((string) $instance->maxReplicas);

        $this->form()->define(
            'scaling_type',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Scaling type',
                    options: [
                        'none' => 'None',
                        'custom' => 'Custom',
                        'auto' => 'Auto',
                    ],
                    default: $value ?? $instance->scalingType,
                    required: true,
                ),
            ),
            'scaling-type',
        )->setPreviousValue($instance->scalingType);

        $this->form()->define(
            'uses_scheduler',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scheduler?',
                    default: false,
                ),
            ),
            'uses-scheduler',
        )->setPreviousValue($instance->usesScheduler);

        $this->form()->define(
            'scaling_cpu_threshold_percentage',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scaling CPU threshold percentage',
                    default: (string) ($value ?? $instance->scalingCpuThresholdPercentage),
                    min: 50,
                    max: 95,
                ),
            ),
            'scaling-cpu-threshold-percentage',
        )->setPreviousValue((string) $instance->scalingCpuThresholdPercentage)->setLabel('Scaling CPU threshold percentage');

        $this->form()->define(
            'scaling_memory_threshold_percentage',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scaling memory threshold percentage',
                    default: (string) ($value ?? $instance->scalingMemoryThresholdPercentage),
                    min: 50,
                    max: 95,
                ),
            ),
            'scaling-memory-threshold-percentage',
        )->setPreviousValue((string) $instance->scalingMemoryThresholdPercentage);

        $this->form()->define(
            'uses_octane',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use Octane?',
                    default: false,
                ),
            ),
            'uses-octane',
        )->setPreviousValue($instance->environment?->usesOctane)->setLabel('Octane');

        $this->form()->define(
            'uses_inertia_ssr',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use Inertia SSR?',
                    default: false,
                ),
            ),
            'uses-inertia-ssr',
        )->setLabel('Inertia SSR');

        $this->form()->define(
            'scale_to_zero',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scale to zero?',
                    default: $value ?? $instance->hibernationTimeout !== null,
                ),
            ),
            'scale-to-zero',
        )->setPreviousValue($instance->hibernationTimeout !== null)->setLabel('Scale to zero');

        $this->form()->define(
            'hibernation_timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scale to zero timeout',
                    default: (string) ($value ?? $instance->hibernationTimeout ?? ''),
                    required: true,
                    hint: 'Minutes without HTTP requests before the app scales to zero',
                ),
            ),
            'scale-to-zero-timeout',
        )->setPreviousValue($instance->hibernationTimeout)->setLabel('Scale to zero timeout');

        $this->form()->define(
            'sleep_with_app',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Scale worker replicas to zero alongside the app?',
                    default: $value ?? $instance->sleepWithApp ?? false,
                ),
            ),
            'sleep-with-app',
        )->setPreviousValue($instance->sleepWithApp)->setLabel('Sleep with app');

        $this->form()->define(
            'probes',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Health checks (JSON)',
                    default: (string) ($value ?? ($instance->probes === null ? '' : json_encode($instance->probes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))),
                    required: true,
                    validate: fn ($json) => $this->validateProbes($json),
                    hint: 'Keyed by startup, readiness, liveness. Replaces all existing checks; enter null to remove them.',
                ),
            ),
        )->setPreviousValue($instance->probes === null ? null : json_encode($instance->probes, JSON_UNESCAPED_SLASHES))->setLabel('Health checks');
    }

    /**
     * False means turn hibernation off, null means leave it unchanged.
     */
    protected function resolveHibernationTimeout(EnvironmentInstance $instance): int|false|null
    {
        $enabled = $this->form()->boolean('scale_to_zero');
        $timeout = $this->form()->integer('hibernation_timeout');

        if ($enabled === false) {
            return false;
        }

        if ($timeout !== null) {
            return $timeout;
        }

        if ($enabled !== true || $instance->hibernationTimeout !== null) {
            return null;
        }

        // Cloud turns hibernation on by receiving a timeout, so enabling it from off needs one.
        if (! $this->isInteractive()) {
            $this->outputErrorOrThrow('Provide --scale-to-zero-timeout to turn on scale to zero.');

            throw new CommandExitException(self::FAILURE);
        }

        $this->form()->prompt('hibernation_timeout');

        return $this->form()->integer('hibernation_timeout');
    }

    /**
     * False means remove the custom checks, null means leave them unchanged.
     *
     * @return array<string, mixed>|false|null
     */
    protected function resolveProbes(): array|false|null
    {
        $json = $this->form()->get('probes');

        if ($json === null) {
            return null;
        }

        $error = $this->validateProbes($json);

        if ($error !== null) {
            $this->outputErrorOrThrow("Invalid --probes value: {$error}");

            throw new CommandExitException(self::FAILURE);
        }

        return json_decode($json, true) ?? false;
    }

    protected function validateProbes(string $json): ?string
    {
        try {
            $probes = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return 'must be valid JSON.';
        }

        if ($probes === null) {
            return null;
        }

        if (! is_array($probes) || array_is_list($probes)) {
            return 'must be a JSON object keyed by startup, readiness, or liveness, or null to remove them.';
        }

        foreach ($probes as $name => $probe) {
            if (! in_array($name, ['startup', 'readiness', 'liveness'], true)) {
                return "unknown check \"{$name}\". Use startup, readiness, or liveness.";
            }

            if ($probe === null) {
                continue;
            }

            if (! is_array($probe) || ($probe !== [] && array_is_list($probe))) {
                return "the {$name} check must be an object or null.";
            }

            foreach ($probe as $field => $setting) {
                if (! in_array($field, ['path', 'port', 'delay', 'interval', 'timeout', 'tries'], true)) {
                    return "unknown field \"{$field}\" in the {$name} check. Use path, port, delay, interval, timeout, or tries.";
                }

                $valid = $setting === null || ($field === 'path' ? is_string($setting) : is_int($setting));

                if (! $valid) {
                    $expected = $field === 'path' ? 'a string' : 'an integer';

                    return "{$name}.{$field} must be {$expected} or null.";
                }
            }
        }

        return null;
    }

    protected function collectDataAndUpdate(EnvironmentInstance $instance): EnvironmentInstance
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

        return $this->updateInstance($instance);
    }
}
