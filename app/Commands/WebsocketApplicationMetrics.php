<?php

namespace App\Commands;

use App\Concerns\RendersMetrics;
use App\Dto\WebsocketMetrics;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationMetrics extends BaseCommand
{
    use RendersMetrics;

    protected ?string $jsonDataClass = WebsocketMetrics::class;

    protected $signature = 'websocket-application:metrics
                            {application? : The application ID or name}
                            {--period=24h : Time period (6h, 24h, 3d, 7d, 30d)}';

    protected $description = 'View WebSocket application metrics';

    protected $aliases = ['ws-app:metrics'];

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Application Metrics');

        $period = $this->metricPeriod();

        $app = $this->resolvers()->websocketApplication()->from($this->argument('application'));

        $metrics = spin(
            fn () => $this->client->websocketApplications()->metrics($app->id, $period),
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
