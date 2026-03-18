<?php

namespace App\Commands;

use Carbon\CarbonImmutable;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class Debug extends BaseCommand
{
    protected $signature = 'debug
                            {application? : Application ID or name}
                            {environment? : Environment ID or name}
                            {--json : Output as JSON}';

    protected $description = 'Show diagnostic information for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Diagnostics');

        $app = $this->resolvers()->application()->from($this->argument('application'));
        $environment = $this->resolvers()->environment()->withApplication($app)->from($this->argument('environment'));

        $diagnostics = spin(function () use ($environment) {
            return $this->gatherDiagnostics($environment);
        }, 'Gathering diagnostics...');

        $this->outputJsonIfWanted($diagnostics);

        $this->displayDiagnostics($diagnostics, $environment);
    }

    protected function gatherDiagnostics(mixed $environment): array
    {
        $diagnostics = [
            'environment' => [
                'id' => $environment->id,
                'name' => $environment->name,
                'status' => $environment->status,
                'url' => $environment->url,
                'php_version' => $environment->phpMajorVersion,
                'instance_count' => count($environment->instances ?? []),
                'env_var_count' => count($environment->environmentVariables ?? []),
            ],
            'deployments' => [],
            'recent_errors' => [],
            'databases' => [],
            'caches' => [],
        ];

        // Fetch last 3 deployments
        try {
            $deployments = $this->client->deployments()->list($environment->id);
            $items = $deployments->collect()->take(3);

            $diagnostics['deployments'] = $items->map(fn ($d) => [
                'id' => $d->id,
                'status' => $d->status->value,
                'commit' => $d->commitHash ? substr($d->commitHash, 0, 7) : null,
                'started_at' => $d->startedAt?->toDateTimeString(),
                'finished_at' => $d->finishedAt?->toDateTimeString(),
            ])->values()->toArray();
        } catch (\Throwable) {
            // Continue gathering other diagnostics
        }

        // Fetch recent logs (last 5 minutes), filter for errors
        try {
            $from = CarbonImmutable::now()->subMinutes(5);
            $to = CarbonImmutable::now();
            $logs = $this->client->environments()->logs($environment->id, $from, $to);

            $diagnostics['recent_errors'] = collect($logs)
                ->filter(fn ($log) => isset($log['level']) && in_array($log['level'], ['error', 'warning']))
                ->take(10)
                ->map(fn ($log) => [
                    'level' => $log['level'] ?? 'unknown',
                    'message' => $log['message'] ?? '',
                    'logged_at' => $log['logged_at'] ?? null,
                ])
                ->values()
                ->toArray();
        } catch (\Throwable) {
            // Continue gathering other diagnostics
        }

        // Fetch database clusters
        try {
            $databases = $this->client->databaseClusters()->list();
            $diagnostics['databases'] = $databases->collect()->map(fn ($db) => [
                'id' => $db->id,
                'name' => $db->name,
                'type' => $db->type,
                'status' => $db->status,
                'region' => $db->region,
            ])->values()->toArray();
        } catch (\Throwable) {
            // Continue gathering other diagnostics
        }

        // Fetch caches
        try {
            $caches = $this->client->caches()->list();
            $diagnostics['caches'] = $caches->collect()->map(fn ($cache) => [
                'id' => $cache->id,
                'name' => $cache->name,
                'type' => $cache->type,
                'status' => $cache->status,
                'region' => $cache->region,
            ])->values()->toArray();
        } catch (\Throwable) {
            // Continue gathering other diagnostics
        }

        return $diagnostics;
    }

    protected function displayDiagnostics(array $diagnostics, mixed $environment): void
    {
        // Environment overview
        intro('Environment');

        dataList([
            'ID' => $diagnostics['environment']['id'],
            'Name' => $diagnostics['environment']['name'],
            'Status' => $diagnostics['environment']['status'] ?? 'N/A',
            'URL' => $diagnostics['environment']['url'] ?: 'N/A',
            'PHP Version' => $diagnostics['environment']['php_version'],
            'Instances' => (string) $diagnostics['environment']['instance_count'],
            'Environment Variables' => (string) $diagnostics['environment']['env_var_count'],
        ]);

        // Recent deployments
        intro('Recent Deployments');

        if (empty($diagnostics['deployments'])) {
            warning('No deployments found.');
        } else {
            dataTable(
                headers: ['ID', 'Status', 'Commit', 'Started', 'Finished'],
                rows: collect($diagnostics['deployments'])->map(fn ($d) => [
                    $d['id'],
                    $d['status'],
                    $d['commit'] ?? 'N/A',
                    $d['started_at'] ?? 'N/A',
                    $d['finished_at'] ?? 'N/A',
                ])->toArray(),
            );
        }

        // Recent errors
        intro('Recent Errors (last 5 min)');

        if (empty($diagnostics['recent_errors'])) {
            $this->line('  <info>No recent errors found.</info>');
        } else {
            foreach ($diagnostics['recent_errors'] as $error) {
                $level = strtoupper($error['level']);
                $this->line("  [{$level}] {$error['message']}");
            }
        }

        // Resources
        intro('Resources');

        if (empty($diagnostics['databases'])) {
            $this->line('  <comment>Databases:</comment> None');
        } else {
            foreach ($diagnostics['databases'] as $db) {
                $this->line("  <comment>Database:</comment> {$db['name']} ({$db['type']}) — {$db['status']}");
            }
        }

        if (empty($diagnostics['caches'])) {
            $this->line('  <comment>Caches:</comment> None');
        } else {
            foreach ($diagnostics['caches'] as $cache) {
                $this->line("  <comment>Cache:</comment> {$cache['name']} ({$cache['type']}) — {$cache['status']}");
            }
        }
    }
}
