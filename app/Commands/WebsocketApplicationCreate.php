<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

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

        $name = $this->option('name') ?? text(
            label: 'Application name',
            required: true,
        );

        $app = spin(
            fn () => $this->client->websocketApplications()->create($cluster->id, ['name' => $name]),
            'Creating WebSocket application...',
        );

        $this->outputJsonIfWanted($app);

        success('WebSocket application created');

        outro("Application created: {$app->name}");
    }
}
