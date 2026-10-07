<?php

namespace App\Commands;

use App\Client\Requests\UpdateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use App\Dto\InstanceProbe;
use App\Enums\InstanceProbeType;
use App\Exceptions\CommandExitException;
use Illuminate\Support\Collection;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

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
                            {--scale-to-zero-timeout= : Scale to zero timeout}
                            {--hibernation= : Deprecated alias for --scale-to-zero}
                            {--hibernation-timeout= : Deprecated alias for --scale-to-zero-timeout}
                            {--startup=* : Startup check settings as key=value (path, port, delay, interval, timeout, tries). Repeat for each setting. Use default to reset}
                            {--readiness=* : Readiness check settings as key=value. Repeat for each setting. Use default to reset}
                            {--liveness=* : Liveness check settings as key=value. Repeat for each setting. Use default to reset}
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
        spin(
            fn () => $this->client->instances()->update(
                new UpdateInstanceRequestData(
                    instanceId: $instance->id,
                    size: $this->form()->get('size'),
                    minReplicas: $this->form()->integer('min_replicas'),
                    maxReplicas: $this->form()->integer('max_replicas'),
                    scalingType: $this->form()->get('scaling_type'),
                    usesScheduler: $this->form()->get('uses_scheduler'),
                    scalingCpuThresholdPercentage: $this->form()->get('scaling_cpu_threshold_percentage'),
                    scalingMemoryThresholdPercentage: $this->form()->get('scaling_memory_threshold_percentage'),
                    usesOctane: $this->form()->get('uses_octane'),
                    usesInertiaSsr: $this->form()->get('uses_inertia_ssr'),
                    usesSleepMode: $this->form()->get('uses_sleep_mode'),
                    sleepTimeout: $this->form()->get('sleep_timeout'),
                    probes: $this->probes($instance),
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
            'uses_sleep_mode',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scale to zero?',
                    default: $value ?? $instance->environment->usesHibernation ?? true,
                ),
            ),
            'scale-to-zero',
        )->setPreviousValue($instance->environment->usesHibernation)->setLabel('Scale to zero');

        $this->form()->define(
            'sleep_timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Scale to zero timeout',
                    default: (string) ($value ?? ''),
                    min: 1,
                    max: 60,
                ),
            ),
            'scale-to-zero-timeout',
        )->setLabel('Scale to zero timeout');

        $this->defineProbeFields($instance);
    }

    protected function defineProbeFields(EnvironmentInstance $instance): void
    {
        $options = $this->options();
        $settings = (new InstanceProbe)->toArray();

        foreach (InstanceProbeType::cases() as $probeType) {
            $probe = $probeType->value;
            $input = $options[$probe];
            $options[$probe] = null;

            if ($input === ['default']) {
                foreach ($settings as $setting => $value) {
                    $options[$probe.'-'.$setting] = '';
                }
            } else {
                foreach ($input as $pair) {
                    $parts = explode('=', $pair, 2);

                    if (count($parts) !== 2 || ! array_key_exists($parts[0], $settings)) {
                        $this->failAndExit('Use --'.$probe.'=key=value with path, port, delay, interval, timeout, or tries. Use default on its own to reset.');
                    }

                    [$setting, $value] = $parts;

                    if ($setting !== 'path' && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $this->failAndExit(ucfirst($probe).' '.$setting.' must be an integer. Leave the value empty to use the platform default.');
                    }

                    $options[$probe.'-'.$setting] = $value;
                }
            }
        }

        $this->form()->options($options);

        $this->form()->define(
            'probes',
            fn ($resolver) => $resolver->fromInput(fn () => $this->promptHealthChecks()),
        )->setLabel('Health checks');

        foreach (InstanceProbeType::cases() as $probeType) {
            $probe = $probeType->value;
            $this->form()->define(
                'probes.'.$probe,
                fn ($resolver) => $resolver->fromInput(fn () => $this->promptProbe($probeType)),
                $probe,
            )->setLabel($probeType->label().' health check');

            foreach ($settings as $setting => $value) {
                $current = $instance->probes[$probe]->{$setting} ?? null;
                $label = $probeType->label().' '.$setting;

                if (in_array($setting, ['delay', 'interval', 'timeout'])) {
                    $label .= ' (seconds)';
                }

                $this->form()->define(
                    'probes.'.$probe.'.'.$setting,
                    fn ($resolver) => $resolver->fromInput(
                        fn ($value) => $setting === 'path'
                            ? text(label: $label, default: (string) ($value ?? $current ?? ''), hint: 'Leave blank to use the platform default.')
                            : number(label: $label, default: (string) ($value ?? $current ?? ''), hint: 'Leave blank to use the platform default.'),
                    ),
                    $probe.'-'.$setting,
                )->setLabel($label)->setPreviousValue($current === null ? '' : (string) $current);
            }
        }
    }

    protected function promptHealthChecks(): string
    {
        $selection = multiselect(
            label: 'Which health checks do you want to update?',
            options: collect(InstanceProbeType::cases())->mapWithKeys(fn (InstanceProbeType $probe) => [$probe->value => $probe->label()])->all(),
            hint: 'Leave probes unselected to keep their settings. Select none to skip health checks.',
        );

        foreach ($selection as $probe) {
            $this->form()->prompt('probes.'.$probe);
        }

        return $selection === [] ? 'Skipped' : 'Updated';
    }

    protected function promptProbe(InstanceProbeType $probeType): string
    {
        $probe = $probeType->value;
        $error = $this->errors?->all()['probes.'.$probe] ?? null;

        foreach ((new InstanceProbe)->toArray() as $setting => $value) {
            $key = 'probes.'.$probe.'.'.$setting;

            if ($error !== null) {
                $this->errors->add($key, $error);
            }

            $this->form()->prompt($key);
        }

        return 'Updated';
    }

    /** @return Collection<string, InstanceProbe>|null */
    protected function probes(EnvironmentInstance $instance): ?Collection
    {
        $probes = new Collection($instance->probes ?? []);
        $filled = $this->form()->filled();
        $changed = false;

        foreach (InstanceProbeType::cases() as $probeType) {
            $probe = $probeType->value;
            $settings = ($probes[$probe] ?? new InstanceProbe)->toArray();

            foreach ($settings as $setting => $value) {
                $key = 'probes.'.$probe.'.'.$setting;

                if (array_key_exists($key, $filled)) {
                    $value = $this->form()->get($key);
                    $settings[$setting] = $value === '' ? null : $value;
                    $changed = true;
                }
            }

            $probes[$probe] = InstanceProbe::from($settings);
        }

        return $changed ? $probes : null;
    }

    protected function collectDataAndUpdate(EnvironmentInstance $instance): EnvironmentInstance
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())
                ->reject(fn ($field) => str_starts_with($field->key, 'probes.'))
                ->mapWithKeys(fn ($field, $key) => [
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

        if ($selection === ['probes'] && $this->probes($instance) === null) {
            $this->outputWarning('No changes selected.');

            throw new CommandExitException(self::SUCCESS);
        }

        return $this->updateInstance($instance);
    }
}
