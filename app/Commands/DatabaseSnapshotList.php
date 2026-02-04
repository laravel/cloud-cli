<?php

namespace App\Commands;

use App\Dto\DatabaseSnapshot;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DatabaseSnapshotList extends BaseCommand
{
    protected $signature = 'database-snapshot:list
                            {database-cluster? : The database cluster ID or name}
                            {--json : Output as JSON}';

    protected $description = 'List database snapshots for a cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Snapshots');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('database-cluster'));

        $snapshots = spin(
            fn () => $this->client->databaseSnapshots()->list($cluster->id),
            'Fetching snapshots...',
        );

        $items = collect($snapshots);

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No snapshots found.');

            return self::FAILURE;
        }

        dataTable(
            headers: ['ID', 'Name', 'Created At', 'Status'],
            rows: $items->map(fn (DatabaseSnapshot $s) => [
                $s->id,
                $s->name,
                $s->createdAt?->toIso8601String() ?? '-',
                $s->status ?? '-',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('database-snapshot:get', [
                        'database-cluster' => $cluster->id,
                        'snapshot' => $row[0],
                    ]),
                    'View',
                ],
            ],
        );
    }
}
