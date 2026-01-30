<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
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

        intro('Environments');

        $applicationId = $this->argument('application');

        if (! $applicationId) {
            $applications = spin(
                fn () => $this->client->applications()->withDefaultIncludes()->list(),
                'Fetching applications...',
            );

            $app = $this->getCloudApplication($applications->collect());
            $applicationId = $app->id;
            answered('Application', $app->name);
        }

        $environments = spin(
            fn () => $this->client->environments()->list($applicationId),
            'Fetching environments...',
        );

        $envItems = $environments->collect();

        $this->outputJsonIfWanted($envItems);

        if ($envItems->isEmpty()) {
            info('No environments found.');

            return;
        }

        table(
            ['ID', 'Name', 'Branch', 'Status', 'URL'],
            $envItems->map(fn ($env) => [
                $env->id,
                $env->name,
                $env->branch ?? 'N/A',
                $env->status ?? 'N/A',
                $env->url ?: 'N/A',
            ])->toArray(),
        );
    }
}
