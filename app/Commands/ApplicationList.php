<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
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

        if (! $this->option('json')) {
            $this->intro('Applications');
        }

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Fetching applications...'
        );

        if ($this->option('json')) {
            $this->line($applications->toJson());

            return;
        }

        if (count($applications->data) === 0) {
            info('No applications found.');

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
