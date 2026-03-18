<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentList extends BaseCommand
{
    protected $aliases = ['envs'];

    protected $signature = 'environment:list
                            {application? : The application ID or name}
                            {--json : Output as JSON}';

    protected $description = 'List all environments for an application';

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

        if ($envItems->isEmpty()) {
            $this->failAndExit('No environments found.');
        }

        $this->outputJsonIfWanted($envItems);

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
