<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseSnapshotGet extends BaseCommand
{
    protected $signature = 'database-snapshot:get
                            {database-cluster? : The database cluster ID or name}
                            {snapshot? : The snapshot ID or name}
                            {--json : Output as JSON}';

    protected $description = 'Get database snapshot details';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Snapshot Details');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('database-cluster'));
        $snapshot = $this->resolvers()->databaseSnapshot()->from($cluster, $this->argument('snapshot'));

        $snapshot = spin(
            fn () => $this->client->databaseSnapshots()->get($cluster->id, $snapshot->id),
            'Fetching snapshot...',
        );

        $this->outputJsonIfWanted($snapshot);

        dataList([
            'ID' => $snapshot->id,
            'Name' => $snapshot->name,
            'Created At' => $snapshot->createdAt?->toIso8601String() ?? '-',
            'Status' => $snapshot->status ?? '-',
        ]);
    }
}
