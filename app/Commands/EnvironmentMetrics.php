<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentMetrics extends BaseCommand
{
    protected $signature = 'environment:metrics {environment? : The environment ID or name} {--json : Output as JSON}';

    protected $description = 'Get environment metrics';

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Metrics');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $metrics = spin(
            fn () => $this->client->environments()->metrics($environment->id),
            'Fetching environment metrics...',
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
