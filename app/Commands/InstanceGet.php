<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class InstanceGet extends BaseCommand
{
    use HasAClient;

    protected $signature = 'instance:get {instance : The instance ID} {--json : Output as JSON}';

    protected $description = 'Get instance details';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Instance Details');

        $instance = spin(
            fn () => $this->client->getInstance($this->argument('instance')),
            'Fetching instance...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $instance->id,
                'name' => $instance->name,
                'type' => $instance->type,
                'size' => $instance->size,
                'scaling_type' => $instance->scalingType,
                'min_replicas' => $instance->minReplicas,
                'max_replicas' => $instance->maxReplicas,
                'uses_scheduler' => $instance->usesScheduler,
                'scaling_cpu_threshold' => $instance->scalingCpuThresholdPercentage,
                'scaling_memory_threshold' => $instance->scalingMemoryThresholdPercentage,
                'background_process_ids' => $instance->backgroundProcessIds,
                'created_at' => $instance->createdAt?->toIso8601String(),
                'updated_at' => $instance->updatedAt?->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        info("Instance: {$instance->name}");
        $this->line("ID: {$instance->id}");
        $this->line("Type: {$instance->type}");
        $this->line("Size: {$instance->size}");
        $this->line("Replicas: {$instance->minReplicas}-{$instance->maxReplicas}");
        $this->line('Background Processes: '.count($instance->backgroundProcessIds));
    }
}
