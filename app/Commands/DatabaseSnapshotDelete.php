<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseSnapshotDelete extends BaseCommand
{
    protected $signature = 'database-snapshot:delete
                            {cluster? : The database cluster ID or name}
                            {snapshot? : The snapshot ID or name}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Delete a database snapshot';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Database Snapshot');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $snapshot = $this->resolvers()->databaseSnapshot()->from($cluster, $this->argument('snapshot'));

        if (! $this->option('force') && ! confirm("Delete snapshot '{$snapshot->name}'?", default: false)) {
            error('Cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->databaseSnapshots()->delete($cluster->id, $snapshot->id),
            'Deleting snapshot...',
        );

        $this->outputJsonIfWanted('Snapshot deleted.');

        success('Snapshot deleted');
    }
}
