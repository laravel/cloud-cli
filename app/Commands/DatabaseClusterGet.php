<?php

namespace App\Commands;

use function Laravel\Prompts\intro;

class DatabaseClusterGet extends BaseCommand
{
    protected $signature = 'database-cluster:get {cluster? : The cluster ID or name} {--json : Output as JSON}';

    protected $description = 'Get database cluster details';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Cluster Details');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $this->outputJsonIfWanted($cluster);

        dataList([
            'ID' => $cluster->id,
            'Name' => $cluster->name,
            'Type' => $cluster->type,
            'Status' => $cluster->status,
            'Region' => $cluster->region,
            'Schemas' => collect($cluster->schemas)->pluck('name')->toArray(),
        ]);
    }
}
