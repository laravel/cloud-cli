<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationDelete extends BaseCommand
{
    protected $signature = 'websocket-application:delete
                            {cluster? : The WebSocket cluster ID or name}
                            {application? : The application ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a WebSocket application';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting WebSocket Application');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));
        $app = $this->resolvers()->websocketApplication()->from($cluster, $this->argument('application'));

        if (! $this->option('force') && ! confirm("Delete WebSocket application \"{$app->name}\"?", default: false)) {
            error('Delete cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->websocketApplications()->delete($cluster->id, $app->id),
            'Deleting WebSocket application...',
        );

        success('WebSocket application deleted');
    }
}
