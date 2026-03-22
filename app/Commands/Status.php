<?php

namespace App\Commands;

use App\Dto\Cache;
use App\Dto\DatabaseCluster;
use App\Dto\Deployment;
use Carbon\CarbonImmutable;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class Status extends BaseCommand
{
    protected $signature = 'status {application? : The application ID or name} {--json : Output as JSON}';

    protected $description = 'Show application health and status overview';

    public function handle()
    {
        $this->ensureClient();

        intro('Application Status');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        $environments = collect($application->environments);

        $latestDeployments = spin(function () use ($environments) {
            return $environments->mapWithKeys(function ($env) {
                $deployments = $this->client->deployments()->list($env->id)->collect();

                return [$env->id => $deployments->first()];
            });
        }, 'Fetching deployments...');

        $databases = spin(
            fn () => $this->client->databaseClusters()->list()->collect(),
            'Fetching databases...',
        );

        $caches = spin(
            fn () => $this->client->caches()->list()->collect(),
            'Fetching caches...',
        );

        if ($this->wantsJson()) {
            $this->outputJsonIfWanted([
                'application' => $application->toArray(),
                'environments' => $environments->map(function ($env) use ($latestDeployments) {
                    $deployment = $latestDeployments->get($env->id);

                    return [
                        'id' => $env->id,
                        'name' => $env->name,
                        'status' => $env->status,
                        'latest_deployment' => $deployment ? [
                            'id' => $deployment->id,
                            'status' => $deployment->status->value,
                            'started_at' => $deployment->startedAt?->toISOString(),
                        ] : null,
                    ];
                })->values()->toArray(),
                'databases' => $databases->map(fn (DatabaseCluster $db) => [
                    'id' => $db->id,
                    'name' => $db->name,
                    'status' => $db->status,
                ])->values()->toArray(),
                'caches' => $caches->map(fn (Cache $cache) => [
                    'id' => $cache->id,
                    'name' => $cache->name,
                    'status' => $cache->status,
                ])->values()->toArray(),
            ]);

            return;
        }

        $this->newLine();
        $this->line("  <options=bold>{$application->name}</>");

        foreach ($environments as $env) {
            /** @var Deployment|null $deployment */
            $deployment = $latestDeployments->get($env->id);
            $deployInfo = $this->formatDeploymentInfo($deployment);
            $this->line("    {$env->name}: {$env->status}{$deployInfo}");
        }

        if ($databases->isNotEmpty()) {
            $this->newLine();
            foreach ($databases as $db) {
                $this->line("  Database: {$db->name} ({$db->status})");
            }
        }

        if ($caches->isNotEmpty()) {
            $this->newLine();
            foreach ($caches as $cache) {
                $this->line("  Cache: {$cache->name} ({$cache->status})");
            }
        }

        if ($databases->isEmpty() && $caches->isEmpty()) {
            $this->newLine();
            $this->line('  No databases or caches found.');
        }

        $this->newLine();
    }

    protected function formatDeploymentInfo(?Deployment $deployment): string
    {
        if (! $deployment) {
            return '';
        }

        $parts = [];

        if ($deployment->startedAt) {
            $parts[] = 'last deploy: '.$deployment->startedAt->diffForHumans(CarbonImmutable::now(), CarbonImmutable::DIFF_ABSOLUTE).' ago';
        }

        $parts[] = $deployment->status->label();

        return ' ('.implode(', ', $parts).')';
    }
}
