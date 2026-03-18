<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseList extends BaseCommand
{
    protected $signature = 'database:list
                            {cluster? : The database cluster ID or name}
                            {--json : Output as JSON}';

    protected $description = 'List all databases (schemas) in a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Databases');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $databases = spin(
            fn () => $this->client->databases()->list($cluster->id)->collect(),
            'Fetching databases...',
        );

        if ($databases->isEmpty()) {
            $this->failAndExit('No databases found.');
        }

        $this->outputJsonIfWanted($databases->toArray());

        dataTable(
            headers: ['ID', 'Name', 'Created At'],
            rows: $databases->map(fn ($database) => [
                $database->id,
                $database->name,
                $database->createdAt?->toIso8601String() ?? '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('database:get', [
                        'cluster' => $cluster->id,
                        'database' => $row[0],
                    ]),
                    'View',
                ],
            ],
        );
    }
}
