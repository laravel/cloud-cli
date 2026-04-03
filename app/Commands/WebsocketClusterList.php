<?php

namespace App\Commands;

use App\Dto\WebsocketCluster;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class WebsocketClusterList extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketCluster::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'websocket-cluster:list';

    protected $description = 'List WebSocket clusters';

    protected $aliases = ['ws-cluster:list'];

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Clusters');

        answered('Organization', $this->client->meta()->organization()->name);

        $clusters = spin(
            fn () => $this->client->websocketClusters()->list(),
            'Fetching WebSocket clusters...',
        );

        $items = $clusters->collect();

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No WebSocket clusters found.');

            return self::FAILURE;
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
