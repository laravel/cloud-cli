<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\EnvironmentInstance;
use App\Enums\InstanceScalingType;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class InstanceList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'instance:list
                            {environment? : The environment ID or name}
                            {--name= : Filter by name}
                            {--type= : Filter by type (app|service|queue|managed_queue)}
                            {--size= : Filter by size}
                            {--scaling-type= : Filter by scaling type (none|custom|auto)}';

    protected $description = 'List all instances for an environment';

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'name' => $this->filterOption('name'),
            'type' => $this->filterOption('type', ['app', 'service', 'queue', 'managed_queue']),
            'size' => $this->filterOption('size'),
            'scalingType' => $this->filterOption('scaling-type', InstanceScalingType::class),
        ];

        intro('Instances');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $items = $this->fetchList(
            fn () => $this->client->instances()->list($environment->id, ...$filters),
            'Fetching instances...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No instances found.');

            return self::SUCCESS;
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
