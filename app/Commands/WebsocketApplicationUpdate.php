<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class WebsocketApplicationUpdate extends BaseCommand
{
    protected $signature = 'websocket-application:update
                            {cluster? : The WebSocket cluster ID or name}
                            {application? : The application ID or name}
                            {--name= : Application name}
                            {--json : Output as JSON}';

    protected $description = 'Update a WebSocket application';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));
        $app = $this->resolvers()->websocketApplication()->from($cluster, $this->argument('application'));

        if (! $this->option('name') && ! $this->isInteractive()) {
            $this->outputErrorOrThrow('Provide --name to update.');

            exit(self::FAILURE);
        }

        $name = $this->option('name') ?? text(
            label: 'Application name',
            default: $app->name,
            required: true,
        );

        $updated = spin(
            fn () => $this->client->websocketApplications()->update($cluster->id, $app->id, ['name' => $name]),
            'Updating WebSocket application...',
        );

        $this->outputJsonIfWanted($updated);

        success('WebSocket application updated');
    }
}
