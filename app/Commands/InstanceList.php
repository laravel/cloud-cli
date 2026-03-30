<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstanceList extends BaseCommand
{
    protected $signature = 'instance:list
                            {environment? : The environment ID or name}
                            {--json : Output as JSON}';

    protected $description = 'List all instances for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Instances');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $instances = spin(
            fn () => $this->client->instances()->list($environment->id),
            'Fetching instances...',
        );

        $items = $instances->collect();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No instances found.');

            return self::FAILURE;
        }

        dataTable(
            headers: ['ID', 'Name', 'Type', 'Size', 'Replicas', 'Scheduler'],
            rows: $items->map(fn ($instance) => [
                $instance->id,
                $instance->name,
                $instance->type,
                $instance->size,
                $instance->minReplicas === $instance->maxReplicas ? $instance->minReplicas : "{$instance->minReplicas}-{$instance->maxReplicas}",
                $instance->usesScheduler ? 'Yes' : 'No',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('instance:get', ['instance' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
