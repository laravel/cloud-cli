<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class InstanceList extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'instance:list {environment : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all instances for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Listing instances');

        $instances = spin(
            fn () => $this->client->listInstances($this->argument('environment')),
            'Fetching instances...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($instance) => [
                    'id' => $instance->id,
                    'name' => $instance->name,
                    'type' => $instance->type,
                    'size' => $instance->size,
                    'scaling_type' => $instance->scalingType,
                    'min_replicas' => $instance->minReplicas,
                    'max_replicas' => $instance->maxReplicas,
                    'created_at' => $instance->createdAt?->toIso8601String(),
                ], $instances->data),
                'links' => $instances->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($instances->data) === 0) {
            $this->info('No instances found.');

            return;
        }

        table(
            ['ID', 'Name', 'Type', 'Size', 'Replicas'],
            collect($instances->data)->map(fn ($instance) => [
                $instance->id,
                $instance->name,
                $instance->type,
                $instance->size,
                "{$instance->minReplicas}-{$instance->maxReplicas}",
            ])->toArray()
        );
    }
}
