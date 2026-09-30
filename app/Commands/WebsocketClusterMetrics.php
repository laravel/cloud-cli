<?php

namespace App\Commands;

use App\Concerns\RendersMetrics;
use App\Dto\WebsocketMetrics;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketClusterMetrics extends BaseCommand
{
    use RendersMetrics;

    protected ?string $jsonDataClass = WebsocketMetrics::class;

    protected $signature = 'websocket-cluster:metrics
                            {cluster? : The cluster ID or name}
                            {--period=24h : Time period (6h, 24h, 3d, 7d, 30d)}';

    protected $description = 'View WebSocket cluster metrics';

    protected $aliases = ['ws-cluster:metrics'];

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Cluster Metrics');

        $period = $this->metricPeriod();

        $cluster = $this->resolvers()->websocketCluster()->from($this->argument('cluster'));

        $metrics = spin(
            fn () => $this->client->websocketClusters()->metrics($cluster->id, $period),
            'Fetching metrics...',
        );

        $this->outputJsonIfWanted($metrics);

        $this->renderMetrics($metrics->period ?? $period, $metrics->timeRange(), [
            'Connections' => $this->seriesLines($metrics->connectionCount, $this->formatCount(), ['current' => 'current', 'average' => 'average', 'max' => 'max']),
            'Message Rate' => $this->seriesLines($metrics->messageRate, $this->formatDecimal(), ['average' => 'average', 'max' => 'max']),
        ]);

        return self::SUCCESS;
    }
}
