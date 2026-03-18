<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseClusterList extends BaseCommand
{
    protected $signature = 'database-cluster:list {--json : Output as JSON}';

    protected $description = 'List all database clusters';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Clusters');

        $databases = spin(
            fn () => $this->client->databaseClusters()->include('schemas')->list(),
            'Fetching databases...',
        );

        $items = $databases->collect();

        if ($items->isEmpty()) {
            $this->failAndExit('No databases found.');
        }

        $this->outputJsonIfWanted($items);

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
