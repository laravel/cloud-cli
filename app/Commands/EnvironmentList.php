<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Environment;
use App\Enums\EnvironmentStatus;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class EnvironmentList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Environment::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'environment:list
                            {application? : The application ID or name}
                            {--name= : Filter by name}
                            {--status= : Filter by status (deploying|running|hibernating|stopped)}
                            {--slug= : Filter by slug}';

    protected $description = 'List all environments for an application';

    protected $aliases = ['env:list'];

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'name' => $this->filterOption('name'),
            'status' => $this->filterOption('status', EnvironmentStatus::class),
            'slug' => $this->filterOption('slug'),
        ];

        intro('Environments');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        answered('Application', $application->name);

        $envItems = $this->fetchList(
            fn () => $this->client->environments()->list($application->id, ...$filters),
            'Fetching environments...',
        );

        $this->outputJsonIfWanted($envItems);

        if ($envItems->isEmpty()) {
            warning('No environments found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Branch', 'Status'],
            rows: $envItems->map(fn ($env) => [
                $env->id,
                $env->displayName(),
                $env->branch ?? '—',
                $env->status ? $env->statusEnum->label() : '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('environment:get', ['environment' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
