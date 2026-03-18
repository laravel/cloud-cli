<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class WebsocketApplicationMetrics extends BaseCommand
{
    protected $signature = 'websocket-application:metrics
                            {application? : The application ID or name}
                            {--json : Output as JSON}';

    protected $description = 'Get WebSocket application metrics';

    public function handle()
    {
        $this->ensureClient();

        intro('WebSocket Application Metrics');

        $app = $this->resolvers()->websocketApplication()->from($this->argument('application'));

        $metrics = spin(
            fn () => $this->client->websocketApplications()->metrics($app->id),
            'Fetching WebSocket application metrics...',
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
