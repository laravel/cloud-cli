<?php

namespace App\Commands;

use App\Dto\EdgeNetwork;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class EdgeNetworkList extends BaseCommand
{
    protected ?string $jsonDataClass = EdgeNetwork::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'edge-network:list
                            {--name= : Filter by name}
                            {--domain= : Filter by domain}
                            {--status= : Filter by status, e.g. available}';

    protected $description = 'List edge networks, including the shared edge network';

    public function handle()
    {
        $this->ensureClient();

        intro('Edge Networks');

        $items = $this->fetchList(
            fn () => $this->client->edgeNetworks()->list(
                $this->option('name'),
                $this->option('domain'),
                $this->option('status'),
            ),
            'Fetching edge networks...',
        );

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No edge networks found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Domain', 'Tenancy', 'Status'],
            rows: $items->map(fn (EdgeNetwork $network) => [
                $network->id,
                $network->name,
                $network->domain,
                $network->tenancyType,
                $network->status,
            ])->toArray(),
        );
    }
}
