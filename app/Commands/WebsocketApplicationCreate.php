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
                            {--name= : Application name}';

    protected $description = 'Create a WebSocket application';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $defaults = array_filter([
            'name' => $this->option('name'),
        ]);

        $app = $this->loopUntilValid(
            fn () => $this->createWebSocketApplication($cluster, $defaults),
        );

        $this->outputJsonIfWanted($app);

        success("WebSocket application created: {$app->name}");
    }
}
