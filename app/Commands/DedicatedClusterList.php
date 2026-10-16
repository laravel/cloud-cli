<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\DedicatedCluster;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class DedicatedClusterList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = DedicatedCluster::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'dedicated-cluster:list
                            {--region= : Filter by region}
                            {--type= : Filter by type (applications|mysql-databases|rds-databases|redis-caches|elasticache-clusters|reverb-websocket-servers)}
                            {--status= : Filter by status (draft|active|maintenance|inactive)}';

    protected $description = 'List dedicated clusters';

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'region' => $this->filterOption('region'),
            'type' => $this->filterOption('type', ['applications', 'mysql-databases', 'rds-databases', 'redis-caches', 'elasticache-clusters', 'reverb-websocket-servers']),
            'status' => $this->filterOption('status', ['draft', 'active', 'maintenance', 'inactive']),
        ];

        intro('Dedicated Clusters');

        answered('Organization', $this->client->meta()->organization()->name);

        $items = $this->fetchList(
            fn () => $this->client->dedicatedClusters()->list(...$filters),
            'Fetching dedicated clusters...',
        );

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No dedicated clusters found.');

            return self::SUCCESS;
        }

        $rows = $items->map(fn (DedicatedCluster $cluster) => [
            $cluster->id,
            $cluster->name,
            $cluster->region,
            $cluster->status,
        ])->toArray();

        dataTable(
            headers: ['ID', 'Name', 'Region', 'Status'],
            rows: $rows,
        );
    }
}
