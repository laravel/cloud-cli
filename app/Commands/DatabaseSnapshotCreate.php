<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class DatabaseSnapshotCreate extends BaseCommand
{
    protected $signature = 'database-snapshot:create
                            {database-cluster? : The database cluster ID or name}
                            {--json : Output as JSON}';

    protected $description = 'Create a database snapshot';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Database Snapshot');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('database-cluster'));

        $snapshot = spin(
            fn () => $this->client->databaseSnapshots()->create($cluster->id),
            'Creating snapshot...',
        );

        $this->outputJsonIfWanted($snapshot);

        success('Database snapshot created');

        outro("Snapshot created: {$snapshot->name}");
    }
}
