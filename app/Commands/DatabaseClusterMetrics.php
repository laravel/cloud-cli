<?php

namespace App\Commands;

use App\Concerns\RendersMetrics;
use App\Dto\DatabaseClusterMetrics as DatabaseClusterMetricsDto;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseClusterMetrics extends BaseCommand
{
    use RendersMetrics;

    protected ?string $jsonDataClass = DatabaseClusterMetricsDto::class;

    protected $signature = 'database-cluster:metrics
                            {cluster? : The cluster ID or name}
                            {--period=24h : Time period (6h, 24h, 3d, 7d, 30d)}';

    protected $description = 'View database cluster metrics';

    protected $aliases = ['db-cluster:metrics'];

    public function handle()
    {
        $this->ensureClient();

        intro('Database Cluster Metrics');

        $period = $this->metricPeriod();

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $metrics = spin(
            fn () => $this->client->databaseClusters()->metrics($cluster->id, $period),
            'Fetching metrics...',
        );

        $this->outputJsonIfWanted($metrics);

        $this->renderMetrics($metrics->period ?? $period, $metrics->timeRange(), [
            'CPU' => $this->seriesLines($metrics->cpuUsage, $this->formatPercent(), ['average' => 'average', 'max' => 'max']),
            'Compute Hours' => $this->seriesLines($metrics->computeHours, $this->formatDecimal(), ['total' => 'hours total']),
            'Memory' => $this->seriesLines($metrics->memoryUsage, $this->formatBytes(), ['current' => 'current', 'min' => 'min', 'max' => 'max']),
            'Writes' => $this->seriesLines($metrics->writes, $this->formatCount(), ['total' => 'total']),
            'Storage' => $this->seriesLines($metrics->storageUsage, $this->formatBytes(), ['current' => 'current']),
            ...($metrics->replicaLag === []
                ? ['Replica Lag' => [['No replicas']]]
                : $this->seriesListRows('Replica Lag', $metrics->replicaLag, $this->formatDecimal(3), ['average' => 'average'])),
        ]);

        return self::SUCCESS;
    }
}
