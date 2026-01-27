<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class ApplicationList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'application:list {--json : Output as JSON}';

    protected $description = 'List all applications';

    public function handle()
    {
        $this->ensureClient();

        intro('Applications');

        $applications = spin(
            fn () => $this->client->applications()->include('organization', 'environments', 'defaultEnvironment')->list(),
            'Fetching applications...',
        );

        $items = collect($applications->items());

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => $items->map(fn ($app) => [
                    'id' => $app->id,
                    'name' => $app->name,
                    'region' => $app->region,
                    'repository' => $app->repositoryFullName ?? null,
                ])->toArray(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        if ($items->isEmpty()) {
            info('No applications found.');

            return;
        }

        table(
            ['ID', 'Name', 'Region', 'Repository'],
            $items->map(fn ($app) => [
                $app->id,
                $app->name,
                $app->region,
                $app->repositoryFullName ?? 'N/A',
            ])->toArray(),
        );
    }
}
