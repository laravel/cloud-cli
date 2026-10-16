<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Application;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class ApplicationList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Application::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'application:list
                            {--name= : Filter by name}
                            {--region= : Filter by region}
                            {--slug= : Filter by slug}';

    protected $description = 'List all applications';

    protected $aliases = ['app:list'];

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'name' => $this->filterOption('name'),
            'region' => $this->filterOption('region'),
            'slug' => $this->filterOption('slug'),
        ];

        intro('Applications');

        answered('Organization', $this->client->meta()->organization()->name);

        $applications = $this->fetchList(
            fn () => $this->client->applications()->include('organization', 'environments')->list(...$filters),
            'Fetching applications...',
        );

        $this->outputJsonIfWanted($applications);

        if ($applications->isEmpty()) {
            warning('No applications found.');

            return self::SUCCESS;
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
