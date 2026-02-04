<?php

namespace App\Commands;

use function Laravel\Prompts\intro;

class DatabaseClusterGet extends BaseCommand
{
    protected $signature = 'database-cluster:get {database? : The database ID or name} {--json : Output as JSON}';

    protected $description = 'Get database cluster details';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Cluster Details');

        $databaseCluster = $this->resolvers()->databaseCluster()->from($this->argument('database'));

        $this->outputJsonIfWanted($databaseCluster);

        dataList([
            'ID' => $databaseCluster->id,
            'Name' => $databaseCluster->name,
            'Type' => $databaseCluster->type,
            'Status' => $databaseCluster->status,
            'Region' => $databaseCluster->region,
            'Schemas' => collect($databaseCluster->schemas)->pluck('name')->toArray(),
        ]);
    }
}
