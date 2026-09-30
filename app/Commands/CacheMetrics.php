<?php

namespace App\Commands;

use App\Concerns\RendersMetrics;
use App\Dto\CacheMetrics as CacheMetricsDto;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheMetrics extends BaseCommand
{
    use RendersMetrics;

    protected ?string $jsonDataClass = CacheMetricsDto::class;

    protected $signature = 'cache:metrics
                            {cache? : The cache ID or name}
                            {--period=24h : Time period (6h, 24h, 3d, 7d, 30d)}';

    protected $description = 'View cache metrics';

    public function handle()
    {
        $this->ensureClient();

        intro('Cache Metrics');

        $period = $this->metricPeriod();

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $metrics = spin(
            fn () => $this->client->caches()->metrics($cache->id, $period),
            'Fetching metrics...',
        );

        $this->outputJsonIfWanted($metrics);

        $this->renderMetrics($metrics->period ?? $period, $metrics->timeRange(), [
            ...$this->seriesListRows('Hits/Misses', $metrics->hitsAndMisses, $this->formatCount(), ['average' => 'average']),
            ...$this->seriesListRows('Throughput', $metrics->throughput, $this->formatCount(), ['average' => 'average']),
            'Size' => $this->seriesLines($metrics->size, $this->formatBytes(), ['total' => 'total']),
            'Bandwidth' => $this->seriesLines($metrics->bandwidthUsage, $this->formatBytes(), ['total' => 'total']),
        ]);

        return self::SUCCESS;
    }
}
