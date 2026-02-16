<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

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
            fn () => $this->client->dedicatedClusters()->list(),
            'Fetching dedicated clusters...',
        );

        $items = collect($clusters);

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No dedicated clusters found.');

            return self::FAILURE;
        }

        $rows = $items->map(fn ($cluster) => [
            $cluster['id'] ?? '—',
            $cluster['attributes']['name'] ?? $cluster['name'] ?? '—',
            $cluster['attributes']['region'] ?? $cluster['region'] ?? '—',
            $cluster['attributes']['status'] ?? $cluster['status'] ?? '—',
        ])->toArray();

        dataTable(
            headers: ['ID', 'Name', 'Region', 'Status'],
            rows: $rows,
        );
    }
}
