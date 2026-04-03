<?php

namespace App\Commands;

use App\Dto\WebsocketCluster;

use function Laravel\Prompts\intro;

class WebsocketClusterGet extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketCluster::class;

    protected $signature = 'websocket-cluster:get {cluster? : The cluster ID or name}';

    protected $description = 'Get WebSocket cluster details';

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Cluster Details');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $this->outputJsonIfWanted($cluster);

        dataList([
            'ID' => $cluster->id,
            'Name' => $cluster->name,
            'Region' => $cluster->region,
            'Status' => $cluster->status->value ?? $cluster->status->name,
            'Type' => $cluster->type->value ?? $cluster->type->name,
            'Hostname' => $cluster->hostname,
            'Max connections' => $cluster->maxConnections->value ?? $cluster->maxConnections->name,
            'Created At' => $cluster->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
