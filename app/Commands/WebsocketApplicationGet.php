<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationGet extends BaseCommand
{
    protected $signature = 'websocket-application:get
                            {cluster? : The WebSocket cluster ID or name}
                            {application? : The application ID or name}
                            {--json : Output as JSON}';

    protected $description = 'Get WebSocket application details';

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Application Details');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));
        $app = $this->resolvers()->websocketApplication()->from($cluster, $this->argument('application'));

        $app = spin(
            fn () => $this->client->websocketApplications()->get($cluster->id, $app->id),
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
            'Created At' => $app->createdAt?->toIso8601String() ?? '-',
        ]);
    }
}
