<?php

namespace App\Commands;

use App\Dto\WebsocketApplication;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationGet extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketApplication::class;

    protected $signature = 'websocket-application:get
                            {application? : The application ID or name}';

    protected $description = 'Get WebSocket application details';

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Application Details');

        $app = $this->resolvers()->websocketApplication()->from($this->argument('application'));

        $app = spin(
            fn () => $this->client->websocketApplications()->get($app->id),
            'Fetching WebSocket application...',
        );

        $this->outputJsonIfWanted($app);

        dataList([
            'ID' => $app->id,
            'Name' => $app->name,
            'App ID' => $app->appId,
            'Key' => $app->key,
            'Max connections' => $app->maxConnections,
            'Ping interval' => $app->pingInterval,
            'Activity timeout' => $app->activityTimeout,
            'Max message size' => $app->maxMessageSize,
            'Created At' => $app->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
