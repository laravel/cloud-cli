<?php

namespace App\Commands;

use App\Dto\WebsocketCluster;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketClusterList extends BaseCommand
{
    protected $signature = 'websocket-cluster:list {--json : Output as JSON}';

    protected $description = 'List WebSocket clusters';

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

        if ($items->isEmpty()) {
            $this->failAndExit('No WebSocket clusters found.');
        }

        $this->outputJsonIfWanted($items->toArray());

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
