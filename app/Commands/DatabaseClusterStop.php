<?php

namespace App\Commands;

use App\Dto\DatabaseCluster;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseClusterStop extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    protected $signature = 'database-cluster:stop
                            {cluster? : The cluster ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Stop a database cluster';

    protected $aliases = ['db-cluster:stop'];

    public function handle()
    {
        $this->ensureClient();

        intro('Stopping Database Cluster');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $this->confirmDestructive("Stop database cluster '{$cluster->name}'?");

        $stopped = spin(
            fn () => $this->client->databaseClusters()->stop($cluster->id),
            'Stopping database cluster...',
        );

        $this->outputJsonIfWanted($stopped);

        success("Database cluster '{$stopped->name}' stopped.");
    }
}
