<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class InstanceList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'instance:list {environment : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all instances for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Instances');

        $instances = spin(
            fn () => $this->client->instances()->list($this->argument('environment')),
            'Fetching instances...',
        );

        $items = $instances->collect();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            info('No instances found.');

            return;
        }

        table(
            ['ID', 'Name', 'Type', 'Size', 'Replicas'],
            $items->map(fn ($instance) => [
                $instance->id,
                $instance->name,
                $instance->type,
                $instance->size,
                "{$instance->minReplicas}-{$instance->maxReplicas}",
            ])->toArray(),
        );
    }
}
