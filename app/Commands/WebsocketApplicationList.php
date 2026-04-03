<?php

namespace App\Commands;

use App\Dto\WebsocketApplication;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class WebsocketApplicationList extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketApplication::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'websocket-application:list
                            {cluster? : The WebSocket cluster ID or name}';

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

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No WebSocket applications found.');

            return self::FAILURE;
        }

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
