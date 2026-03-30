<?php

namespace App\Commands;

use App\Client\Requests\UpdateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class InstanceUpdate extends BaseCommand
{
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
                            {--hibernation= : Uses hibernation}
                            {--hibernation-timeout= : Hibernation timeout}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update an instance';

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
                    label: 'Use sleep mode?',
                    default: $value ?? $instance->environment->usesHibernation ?? true,
                ),
            ),
            'hibernation',
        )->setPreviousValue($instance->environment->usesHibernation)->setLabel('Hibernation');

        $this->form()->define(
            'sleep_timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Sleep timeout',
                    default: (string) ($value ?? ''),
                    min: 1,
                    max: 60,
                ),
            ),
            'hibernation-timeout',
        )->setLabel('Hibernation timeout');
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
