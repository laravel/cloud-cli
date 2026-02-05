<?php

namespace App\Commands;

use App\Actions\CreateWebSocketApplication;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class WebsocketApplicationCreate extends BaseCommand
{
    protected $signature = 'websocket-application:create
                            {cluster? : The WebSocket cluster ID or name}
                            {--name= : Application name}
                            {--json : Output as JSON}';

    protected $description = 'Create a WebSocket application';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $defaults = array_filter([
            'name' => $this->option('name'),
        ]);

        $app = app(CreateWebSocketApplication::class)->run($this->client, $cluster, $defaults);

        $this->outputJsonIfWanted($app);

        success('WebSocket application created');

        outro("Application created: {$app->name}");
    }
}
