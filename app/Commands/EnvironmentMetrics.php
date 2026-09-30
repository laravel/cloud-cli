<?php

namespace App\Commands;

use App\Concerns\RendersMetrics;
use App\Dto\EnvironmentMetrics as EnvironmentMetricsDto;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentMetrics extends BaseCommand
{
    use RendersMetrics;

    protected ?string $jsonDataClass = EnvironmentMetricsDto::class;

    protected $signature = 'environment:metrics
                            {environment? : The environment ID or name}
                            {--period=24h : Time period (6h, 24h, 3d, 7d, 30d)}';

    protected $description = 'View environment compute and HTTP metrics';

    protected $aliases = ['env:metrics'];

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Metrics');

        $period = $this->metricPeriod();

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $metrics = spin(
            fn () => $this->client->environments()->metrics($environment->id, $period),
            'Fetching metrics...',
        );

        $this->outputJsonIfWanted($metrics);

        $range = ['average' => 'average', 'min' => 'min', 'max' => 'max'];

        $this->renderMetrics($metrics->period ?? $period, $metrics->timeRange(), [
            ...$this->seriesListRows('CPU', $metrics->cpuUsage, $this->formatDecimal(), $range),
            ...$this->seriesListRows('Memory', $metrics->memoryUsage, $this->formatDecimal(), $range),
            ...$this->seriesListRows('HTTP Responses', $metrics->httpResponseCount, $this->formatCount(), ['total' => 'total', ...$range]),
            ...$this->seriesListRows('Replicas', $metrics->replicaCount, $this->formatDecimal(1), $range),
            ...$this->seriesListRows('Web Workers', $metrics->webWorkersCount, $this->formatDecimal(1), $range),
        ]);

        return self::SUCCESS;
    }
}
