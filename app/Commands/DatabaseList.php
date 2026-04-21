<?php

namespace App\Commands;

use App\Dto\Database;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DatabaseList extends BaseCommand
{
    protected ?string $jsonDataClass = Database::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'database:list
                            {cluster? : The database cluster ID or name}';

    protected $description = 'List all databases (schemas) in a database cluster';

    protected $aliases = ['db:list'];

    public function handle()
    {
        $this->ensureClient();

        intro('Databases');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $databases = spin(
            fn () => $this->client->databases()->list($cluster->id)->collect(),
            'Fetching databases...',
        );

        $this->outputJsonIfWanted($databases->toArray());

        if ($databases->isEmpty()) {
            warning('No databases found.');

            return self::SUCCESS;
        }

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
