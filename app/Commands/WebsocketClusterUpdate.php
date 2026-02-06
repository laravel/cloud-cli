<?php

namespace App\Commands;

use App\Client\Requests\UpdateWebSocketClusterRequestData;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class WebsocketClusterUpdate extends BaseCommand
{
    protected $signature = 'websocket-cluster:update
                            {cluster? : The cluster ID or name}
                            {--name= : Cluster name}
                            {--json : Output as JSON}';

    protected $description = 'Update a WebSocket cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating WebSocket Cluster');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        if (! $this->option('name') && ! $this->isInteractive()) {
            $this->outputErrorOrThrow('Provide --name to update.');

            exit(self::FAILURE);
        }

        $name = $this->option('name') ?? text(
            label: 'Cluster name',
            default: $cluster->name,
            required: true,
        );

        $updated = spin(
            fn () => $this->client->websocketClusters()->update(new UpdateWebSocketClusterRequestData(
                clusterId: $cluster->id,
                name: $name,
            )),
            'Updating WebSocket cluster...',
        );

        $this->outputJsonIfWanted($updated);

        success('WebSocket cluster updated');
    }
}
