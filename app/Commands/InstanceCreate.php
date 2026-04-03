<?php

namespace App\Commands;

use App\Client\Requests\CreateInstanceRequestData;
use App\Dto\EnvironmentInstance;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\number;
use function Laravel\Prompts\search;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InstanceCreate extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'instance:create
                            {environment? : The environment ID}
                            {--name= : Instance name}
                            {--type=service : Instance type (app|worker)}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}';

    protected $description = 'Create a new instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Instance');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $instance = $this->loopUntilValid(fn () => $this->createInstance($environment->id));

        $this->outputJsonIfWanted($instance);

        success("Instance created: {$instance->name}");
    }

    protected function createInstance(string $environmentId)
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    default: $value ?? '',
                    required: true,
                ),
            ),
        );

        $sizes = $this->client->instances()->sizes();

        $this->form()->prompt(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => search(
                    label: 'Size',
                    options: fn ($query) => collect($sizes->all())
                        ->filter(
                            fn ($size) => $query === ''
                                || str_contains(strtolower($size->name), strtolower($query))
                                || str_contains(strtolower($size->description), strtolower($query)),
                        )
                        ->mapWithKeys(fn ($size) => [$size->name => $size->description])
                        ->toArray(),
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'scaling_type',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $value ?? (confirm('Enable autoscaling?', default: true) ? 'custom' : 'none'),
            ),
        );

        $isCustom = $this->form()->get('scaling_type') === 'custom';

        $this->form()->prompt(
            'min_replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $isCustom ? number(
                    label: 'Minimum replicas',
                    default: $value ?? '1',
                    min: 1,
                    max: 10,
                ) : 1,
            ),
        );

        $this->form()->prompt(
            'max_replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $isCustom ? number(
                    label: 'Maximum replicas',
                    default: $value ?? $this->form()->get('min_replicas'),
                    min: $this->form()->integer('min_replicas'),
                    max: 10,
                ) : $this->form()->integer('min_replicas'),
            ),
        );

        if ($isCustom) {
            $this->form()->prompt(
                'scaling_cpu_threshold_percentage',
                fn ($resolver) => $resolver->fromInput(fn ($value) => number(
                    label: 'Scaling CPU threshold percentage',
                    default: $value ?? '50',
                    min: 50,
                    max: 95,
                )),
            );

            $this->form()->prompt(
                'scaling_memory_threshold_percentage',
                fn ($resolver) => $resolver->fromInput(fn ($value) => number(
                    label: 'Scaling memory threshold percentage',
                    default: $value ?? '50',
                    min: 50,
                    max: 95,
                )),
            );
        }

        $this->form()->prompt(
            'type',
            fn ($resolver) => $resolver->fromInput(fn () => 'service'),
        );

        $this->form()->prompt(
            'uses_scheduler',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scheduler?',
                    default: false,
                ),
            ),
        );

        return spin(
            fn () => $this->client->instances()->create(
                new CreateInstanceRequestData(
                    environmentId: $environmentId,
                    name: $this->form()->get('name'),
                    type: $this->form()->get('type'),
                    size: $this->form()->get('size'),
                    scalingType: $this->form()->get('scaling_type'),
                    minReplicas: $this->form()->integer('min_replicas'),
                    maxReplicas: $this->form()->integer('max_replicas'),
                    usesScheduler: $this->form()->boolean('uses_scheduler'),
                    scalingCpuThresholdPercentage: $this->form()->integer('scaling_cpu_threshold_percentage'),
                    scalingMemoryThresholdPercentage: $this->form()->integer('scaling_memory_threshold_percentage'),
                ),
            ),
            'Creating instance...',
        );
    }
}
