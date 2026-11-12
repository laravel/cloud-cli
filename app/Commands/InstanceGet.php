<?php

namespace App\Commands;

use App\Dto\EnvironmentInstance;

use function Laravel\Prompts\intro;

class InstanceGet extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'instance:get {instance? : The instance ID}';

    protected $description = 'Get instance details';

    public function handle()
    {
        $this->ensureClient();

        intro('Instance Details');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $this->outputJsonIfWanted($instance);

        dataList(array_filter([
            'ID' => $instance->id,
            'Name' => $instance->name,
            'Type' => $instance->type,
            'Queue Type' => $instance->queueType,
            'Size' => $instance->size,
            'Replicas' => $instance->minReplicas === $instance->maxReplicas ? $instance->minReplicas : "{$instance->minReplicas}-{$instance->maxReplicas}",
            'Scheduler' => $instance->usesScheduler ? 'Yes' : 'No',
            'Octane' => $instance->usesOctane === null ? null : ($instance->usesOctane ? 'Yes' : 'No'),
            'Inertia SSR' => $instance->usesInertiaSsr === null ? null : ($instance->usesInertiaSsr ? 'Yes' : 'No'),
            'Scaling CPU Threshold' => $instance->scalingCpuThresholdPercentage.'%',
            'Scaling Memory Threshold' => $instance->scalingMemoryThresholdPercentage.'%',
            'Background Processes' => count($instance->backgroundProcessIds),
        ], fn ($value) => $value !== null));
    }
}
