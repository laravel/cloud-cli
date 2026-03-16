<?php

namespace App\Commands;

use App\Dto\WebsocketApplication;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationList extends BaseCommand
{
    protected $signature = 'websocket-application:list
                            {cluster? : The WebSocket cluster ID or name}
                            {--json : Output as JSON}';

    protected $description = 'List WebSocket applications for a cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Applications');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $apps = spin(
            fn () => $this->client->websocketApplications()->list($cluster->id)->collect(),
            'Fetching WebSocket applications...',
        );

        $items = collect($apps);

        if ($items->isEmpty()) {
            $this->failAndExit('No WebSocket applications found.');
        }

        $this->outputJsonIfWanted($items->toArray());

        dataTable(
            headers: ['ID', 'Name', 'App ID', 'Max connections', 'Created At'],
            rows: $items->map(fn (WebsocketApplication $a) => [
                $a->id,
                $a->name,
                $a->appId,
                $a->maxConnections,
                $a->createdAt?->toIso8601String() ?? '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('websocket-application:get', [
                        'application' => $row[0],
                    ]),
                    'View',
                ],
            ],
        );
    }
}
