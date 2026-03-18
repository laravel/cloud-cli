<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseClusterMetrics extends BaseCommand
{
    protected $signature = 'database-cluster:metrics {cluster? : The cluster ID or name} {--json : Output as JSON}';

    protected $description = 'Get database cluster metrics';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Cluster Metrics');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $metrics = spin(
            fn () => $this->client->databaseClusters()->metrics($cluster->id),
            'Fetching database cluster metrics...',
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
