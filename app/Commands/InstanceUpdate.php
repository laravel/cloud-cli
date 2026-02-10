<?php

namespace App\Commands;

use App\Client\Requests\UpdateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InstanceUpdate extends BaseCommand
{
    protected $signature = 'instance:update
                            {instance? : The instance ID or name}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}
                            {--scaling-type= : Scaling type}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update an instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Instance');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $this->defineFields($instance);

        foreach ($this->form()->filled() as $key => $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedInstance = $this->resolveUpdatedInstance($instance);

        $this->outputJsonIfWanted($updatedInstance);

        success('Instance updated');

        outro("Instance updated: {$updatedInstance->name}");
    }

    protected function resolveUpdatedInstance(EnvironmentInstance $instance): EnvironmentInstance
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                throw new CommandExitException(self::FAILURE);
            }

            return $this->updateInstance($instance);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($instance),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        return $this->updateInstance($instance);
    }

    protected function updateInstance(EnvironmentInstance $instance): EnvironmentInstance
    {
        $minReplicas = $this->form()->get('min_replicas');
        $maxReplicas = $this->form()->get('max_replicas');

        spin(
            fn () => $this->client->instances()->update(new UpdateInstanceRequestData(
                instanceId: $instance->id,
                size: $this->form()->get('size'),
                minReplicas: $minReplicas !== null ? (int) $minReplicas : null,
                maxReplicas: $maxReplicas !== null ? (int) $maxReplicas : null,
                scalingType: $this->form()->get('scaling_type'),
            )),
            'Updating instance...',
        );

        return $this->client->instances()->get($instance->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the instance?');
    }

    protected function defineFields(EnvironmentInstance $instance): void
    {
        $this->form()->define(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Size',
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
