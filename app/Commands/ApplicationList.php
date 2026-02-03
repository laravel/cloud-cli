<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Dto\Application;
use Laravel\Prompts\Key;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ApplicationList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'application:list {--json : Output as JSON}';

    protected $description = 'List all applications';

    public function handle()
    {
        $this->ensureClient();

        intro('Applications');

        answered('Organization', $this->client->meta()->organization()->name);

        $applications = spin(
            fn () => $this->client->applications()->include('organization', 'environments')->list(),
            'Fetching applications...',
        )->collect();

        $this->outputJsonIfWanted($applications);

        if ($applications->isEmpty()) {
            info('No applications found.');

            return;
        }

        dataTable(
            headers: ['ID', 'Name', 'Region', 'Repository'],
            rows: $applications->map(fn (Application $app) => [
                $app->id,
                $app->name,
                $app->region,
                $app->repositoryFullName ?? '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('application:get', ['application' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
