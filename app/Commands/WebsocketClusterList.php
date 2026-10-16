<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\WebsocketCluster;
use App\Enums\WebsocketServerStatus;
use App\Enums\WebsocketServerType;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class WebsocketClusterList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = WebsocketCluster::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'websocket-cluster:list
                            {--type= : Filter by type (reverb)}
                            {--region= : Filter by region}
                            {--status= : Filter by status (creating|updating|available|stopped|deleting|deleted|unknown)}';

    protected $description = 'List WebSocket clusters';

    protected $aliases = ['ws-cluster:list'];

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'type' => $this->filterOption('type', WebsocketServerType::class),
            'region' => $this->filterOption('region'),
            'status' => $this->filterOption('status', WebsocketServerStatus::class),
        ];

        intro('WebSocket Clusters');

        answered('Organization', $this->client->meta()->organization()->name);

        $items = $this->fetchList(
            fn () => $this->client->websocketClusters()->list(...$filters),
            'Fetching WebSocket clusters...',
        );

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No WebSocket clusters found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Region', 'Status'],
            rows: $items->map(fn (WebsocketCluster $c) => [
                $c->id,
                $c->name,
                $c->region,
                $c->status->value ?? $c->status->name,
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('websocket-cluster:get', ['cluster' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
