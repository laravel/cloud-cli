<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class EnvironmentList extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;

    protected $signature = 'environment:list
                            {application? : The application ID or name}
                            {--json : Output as JSON}';

    protected $description = 'List all environments for an application';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Listing environments');

        $applicationId = $this->argument('application');

        if (! $applicationId) {
            $applications = spin(
                fn () => $this->client->listApplications(),
                'Fetching applications...'
            );

            $app = $this->getCloudApplication(collect($applications->data));
            $applicationId = $app->id;
        }

        $environments = spin(
            fn () => $this->client->listEnvironments($applicationId),
            'Fetching environments...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($env) => [
                    'id' => $env->id,
                    'name' => $env->name,
                    'branch' => $env->branch,
                    'status' => $env->status,
                    'url' => $env->url,
                    'created_at' => $env->createdAt?->toIso8601String(),
                ], $environments->data),
                'links' => $environments->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($environments->data) === 0) {
            info('No environments found.');

            return;
        }

        table(
            ['ID', 'Name', 'Branch', 'Status', 'URL'],
            collect($environments->data)->map(fn ($env) => [
                $env->id,
                $env->name,
                $env->branch ?? 'N/A',
                $env->status ?? 'N/A',
                $env->url ?: 'N/A',
            ])->toArray()
        );
    }
}
