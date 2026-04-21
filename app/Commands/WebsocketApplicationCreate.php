<?php

namespace App\Commands;

use App\Concerns\CreatesWebSocketApplication;
use App\Dto\WebsocketApplication;

use function Laravel\Prompts\intro;

class WebsocketApplicationCreate extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketApplication::class;

    use CreatesWebSocketApplication;

    protected $signature = 'websocket-application:create
                            {cluster? : The WebSocket cluster ID or name}
                            {--name= : Application name}
                            {--ping-interval= : Ping interval in seconds}
                            {--activity-timeout= : Activity timeout in seconds}
                            {--allowed-origins= : Allowed origins (newline-separated)}';

    protected $description = 'Create a WebSocket application';

    protected $aliases = ['ws-app:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $defaults = array_filter([
            'name' => $this->option('name'),
            'ping_interval' => $this->option('ping-interval'),
            'activity_timeout' => $this->option('activity-timeout'),
            'allowed_origins' => $this->option('allowed-origins'),
        ]);

        $app = $this->isInteractive()
            ? $this->loopUntilValid(fn () => $this->createWebSocketApplication($cluster, $defaults))
            : $this->createWebSocketApplicationWithOptions($cluster, $defaults);

        $this->outputJsonIfWanted($app);

        success("WebSocket application created: {$app->name}");
    }
}
