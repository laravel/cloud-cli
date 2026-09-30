<?php

namespace App\Commands;

use App\Dto\DedicatedCluster;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class DedicatedClusterList extends BaseCommand
{
    protected ?string $jsonDataClass = DedicatedCluster::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'dedicated-cluster:list';

    protected $description = 'List dedicated clusters';

    public function handle()
    {
        $this->ensureClient();

        intro('Dedicated Clusters');

        answered('Organization', $this->client->meta()->organization()->name);

        $items = $this->fetchList(
            fn () => $this->client->dedicatedClusters()->list(),
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
