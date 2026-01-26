<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Enums\InstanceSize;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class InstanceCreate extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;

    protected $signature = 'instance:create
                            {environment? : The environment ID}
                            {--name= : Instance name}
                            {--type=service : Instance type (app|worker)}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}
                            {--json : Output as JSON}';

    protected $description = 'Create a new instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Instance');

        $application = $this->getCloudApplication();
        $environment = $this->getEnvironment(collect($application->environments));

        $instance = $this->loopUntilValid(fn () => $this->createInstance($environment->id));

        $this->outputJsonIfWanted($instance);

        outro("Instance created: {$instance->name}");
    }

    protected function createInstance(string $environmentId)
    {
        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    default: $value ?? '',
                    required: true,
                ),
            ),
        );

        $this->addParam(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => search(
                    label: 'Size',
                    options: fn ($query) => collect(InstanceSize::cases())
                        ->map(fn ($size) => $size->value)
                        ->filter(fn ($size) => $query === '' ? true : str_contains($size, $query))
                        ->toArray(),
                    required: true,
                ),
            ),
        );

        $this->addParam(
            'scaling-type',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Scaling type',
                    options: [
                        'none' => 'None',
                        'custom' => 'Custom',
                        'auto' => 'Auto',
                    ],
                    default: $value ?? 'none',
                    required: true,
                ),
            )->paramKey('scaling_type'),
        );

        $isCustom = $this->getParam('scaling-type') === 'custom';

        $this->addParam(
            'min-replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $isCustom ? number(
                    label: 'Minimum replicas',
                    default: $value ?? '1',
                    min: 1,
                    max: 10,
                ) : 1,
            )->paramKey('min_replicas'),
        );

        $this->addParam(
            'max-replicas',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $isCustom ? number(
                    label: 'Maximum replicas',
                    default: $value ?? $this->getParam('min-replicas'),
                    min: $this->getParam('min-replicas'),
                    max: 10,
                ) : $this->getParam('min-replicas'),
            )->paramKey('max_replicas'),
        );

        if ($isCustom) {
            $this->addParam(
                'scaling-cpu-threshold-percentage',
                fn ($resolver) => $resolver->fromInput(fn ($value) => number(
                    label: 'Scaling CPU threshold percentage',
                    default: $value ?? '50',
                    min: 50,
                    max: 95,
                ))->paramKey('scaling_cpu_threshold_percentage'),
            );

            $this->addParam(
                'scaling-memory-threshold-percentage',
                fn ($resolver) => $resolver->fromInput(fn ($value) => number(
                    label: 'Scaling memory threshold percentage',
                    default: $value ?? '50',
                ))->paramKey('scaling_memory_threshold_percentage'),
            );
        }

        $this->addParam(
            'type',
            fn ($resolver) => $resolver->fromInput(fn () => 'service'),
        );

        $this->addParam(
            'uses-scheduler',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => confirm(
                    label: 'Use scheduler?',
                    default: false,
                ),
            )->paramKey('uses_scheduler'),
        );

        return spin(
            fn () => $this->client->createInstance(
                $environmentId,
                $this->getParams(),
            ),
            'Creating instance...',
        );
    }
}
