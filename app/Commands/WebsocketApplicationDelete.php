<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationDelete extends BaseCommand
{
    protected $signature = 'websocket-application:delete
                            {application? : The application ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a WebSocket application';

    protected $aliases = ['ws-app:delete'];

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting WebSocket Application');

        $app = $this->resolvers()->websocketApplication()->from($this->argument('application'));

        $this->confirmDestructive("Delete WebSocket application '{$app->name}'?");

        spin(
            fn () => $this->client->websocketApplications()->delete($app->id),
            'Deleting WebSocket application...',
        );

        $this->outputJsonIfWanted('WebSocket application deleted.');

        success('WebSocket application deleted');
    }
}
