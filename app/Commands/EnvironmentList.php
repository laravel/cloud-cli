<?php

namespace App\Commands;

use App\Dto\Environment;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentList extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'environment:list
                            {application? : The application ID or name}';

    protected $description = 'List all environments for an application';

    protected $aliases = ['env:list'];

    public function handle()
    {
        $this->ensureClient();

        intro('Environments');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        answered('Application', $application->name);

        $environments = spin(
            fn () => $this->client->environments()->list($application->id),
            'Fetching environments...',
        );

        $envItems = $environments->collect();

        $this->outputJsonIfWanted($envItems);

        if ($envItems->isEmpty()) {
            warning('No environments found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Branch', 'Status'],
            rows: $envItems->map(fn ($env) => [
                $env->id,
                $env->name,
                $env->branch ?? '—',
                $env->status ?? '—',
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
