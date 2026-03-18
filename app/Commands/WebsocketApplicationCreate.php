<?php

namespace App\Commands;

use App\Concerns\CreatesWebSocketApplication;

use function Laravel\Prompts\intro;

class WebsocketApplicationCreate extends BaseCommand
{
    use CreatesWebSocketApplication;

    protected $signature = 'websocket-application:create
                            {cluster? : The WebSocket cluster ID or name}
                            {--name= : Application name}
                            {--allowed-origins= : Allowed origins (comma-separated, prefixed with protocol e.g. https://)}
                            {--ping-interval= : Ping interval in seconds (1-60)}
                            {--activity-timeout= : Activity timeout in seconds (1-60)}
                            {--json : Output as JSON}';

    protected $description = 'Create a WebSocket application';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $defaults = array_filter([
            'name' => $this->option('name'),
            'allowed_origins' => $this->option('allowed-origins'),
            'ping_interval' => $this->option('ping-interval'),
            'activity_timeout' => $this->option('activity-timeout'),
        ]);

        $app = $this->loopUntilValid(
            fn () => $this->createWebSocketApplication($cluster, $defaults),
        );

        $this->outputJsonIfWanted($app);

        success("WebSocket application created: {$app->name}");
    }
}
