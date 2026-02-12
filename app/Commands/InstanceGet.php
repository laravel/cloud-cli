<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class InstanceGet extends BaseCommand
{
    protected $signature = 'instance:get {instance : The instance ID} {--json : Output as JSON}';

    protected $description = 'Get instance details';

    public function handle()
    {
        $this->ensureClient();

        intro('Instance Details');

        $instance = spin(
            fn() => $this->client->instances()->get($this->argument('instance')),
            'Fetching instance...',
        );

        $this->outputJsonIfWanted($instance);

        dataList([
            'ID' => $instance->id,
            'Name' => $instance->name,
            'Type' => $instance->type,
            'Size' => $instance->size,
            'Replicas' => $instance->minReplicas === $instance->maxReplicas ? $instance->minReplicas : "{$instance->minReplicas}-{$instance->maxReplicas}",
            'Scheduler' => $instance->usesScheduler ? 'Yes' : 'No',
            'Scaling CPU Threshold' => $instance->scalingCpuThresholdPercentage . '%',
            'Scaling Memory Threshold' => $instance->scalingMemoryThresholdPercentage . '%',
            'Background Processes' => count($instance->backgroundProcessIds),
        ]);
    }
}
