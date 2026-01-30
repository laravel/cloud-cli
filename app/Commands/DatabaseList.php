<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DatabaseList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'database:list {--json : Output as JSON}';

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

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            info('No databases found.');

            return;
        }

        table(
            ['ID', 'Name', 'Type', 'Status', 'Region', 'Schemas'],
            $items->map(fn ($db) => [
                $db->id,
                $db->name,
                $db->type,
                $db->status,
                $db->region,
                collect($db->schemas)->pluck('name')->implode(PHP_EOL),
            ])->toArray(),
        );
    }
}
