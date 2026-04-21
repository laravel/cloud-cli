<?php

namespace App\Commands;

use App\Dto\DatabaseCluster;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DatabaseClusterList extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'database-cluster:list';

    protected $description = 'List all database clusters';

    protected $aliases = ['db-cluster:list'];

    public function handle()
    {
        $this->ensureClient();

        intro('Database Clusters');

        $databases = spin(
            fn () => $this->client->databaseClusters()->include('schemas')->list(),
            'Fetching databases...',
        );

        $items = $databases->collect();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No databases found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Type', 'Status', 'Region', 'Schemas'],
            rows: $items->map(fn ($db) => [
                $db->id,
                $db->name,
                $db->type,
                $db->status,
                $db->region,
                collect($db->schemas)->pluck('name')->implode(PHP_EOL),
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('database-cluster:get', ['database' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
