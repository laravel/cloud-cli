<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class ApplicationList extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'application:list {--json : Output as JSON}';

    protected $description = 'List all applications';

    public function handle()
    {
        $this->ensureClient();

        intro('Listing applications');

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Fetching applications...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($app) => [
                    'id' => $app->id,
                    'name' => $app->name,
                    'slug' => $app->slug,
                    'region' => $app->region,
                    'repository' => $app->repositoryFullName,
                    'default_environment_id' => $app->defaultEnvironmentId,
                    'created_at' => $app->createdAt?->toIso8601String(),
                ], $applications->data),
                'links' => $applications->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($applications->data) === 0) {
            $this->info('No applications found.');

            return;
        }

        table(
            ['ID', 'Name', 'Region', 'Repository'],
            collect($applications->data)->map(fn ($app) => [
                $app->id,
                $app->name,
                $app->region,
                $app->repositoryFullName ?? 'N/A',
            ])->toArray()
        );
    }
}
