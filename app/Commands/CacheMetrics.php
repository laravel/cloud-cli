<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheMetrics extends BaseCommand
{
    protected $signature = 'cache:metrics {cache? : The cache ID or name} {--json : Output as JSON}';

    protected $description = 'Get cache metrics';

    public function handle()
    {
        $this->ensureClient();

        intro('Cache Metrics');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $metrics = spin(
            fn () => $this->client->caches()->metrics($cache->id),
            'Fetching cache metrics...',
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
