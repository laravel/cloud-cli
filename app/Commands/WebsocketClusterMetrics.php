<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketClusterMetrics extends BaseCommand
{
    protected $signature = 'websocket-cluster:metrics {cluster? : The cluster ID or name} {--json : Output as JSON}';

    protected $description = 'Get WebSocket cluster metrics';

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Cluster Metrics');

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $metrics = spin(
            fn () => $this->client->websocketClusters()->metrics($cluster->id),
            'Fetching WebSocket cluster metrics...',
        );

        $this->outputJsonIfWanted($metrics);

        $this->displayMetrics($metrics);
    }

    protected function displayMetrics(array $metrics): void
    {
        if (empty($metrics)) {
            $this->line('No metrics available.');

            return;
        }

        $rows = collect($metrics)->map(function ($value, $key) {
            return [$key, is_array($value) ? json_encode($value) : $value];
        })->values()->toArray();

        $this->table(['Metric', 'Value'], $rows);
    }
}
