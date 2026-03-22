<?php

namespace App\Commands;

use App\Dto\DedicatedCluster;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DedicatedClusterList extends BaseCommand
{
    protected $signature = 'dedicated-cluster:list {--json : Output as JSON}';

    protected $description = 'List dedicated clusters';

    public function handle()
    {
        $this->ensureClient();

        intro('Dedicated Clusters');

        answered('Organization', $this->client->meta()->organization()->name);

        $clusters = spin(
            fn () => $this->client->dedicatedClusters()->list()->collect(),
            'Fetching dedicated clusters...',
        );

        $items = $clusters->collect();

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            $this->failAndExit('No dedicated clusters found.');
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
