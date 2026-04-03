<?php

namespace App\Commands;

use App\Dto\Application;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class ApplicationList extends BaseCommand
{
    protected ?string $jsonDataClass = Application::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'application:list';

    protected $description = 'List all applications';

    protected $aliases = ['app:list'];

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
            warning('No applications found.');

            return self::FAILURE;
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
