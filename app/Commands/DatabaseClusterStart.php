<?php

namespace App\Commands;

use App\Dto\DatabaseCluster;
use App\Enums\DatabaseClusterPreset;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DatabaseClusterStart extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    protected $signature = 'database-cluster:start {cluster? : The cluster ID or name}';

    protected $description = 'Start a stopped database cluster';

    protected $aliases = ['db-cluster:start'];

    public function handle()
    {
        $this->ensureClient();

        intro('Starting Database Cluster');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $started = spin(
            fn () => $this->client->databaseClusters()->start($cluster->id),
            'Starting database cluster...',
        );

        $this->outputJsonIfWanted($started);

        success("Database cluster '{$started->name}' started.");

        // Serverless Postgres comes back on a new hostname, so environments keep
        // the old one in their connection settings until they are redeployed.
        if ($started->type === DatabaseClusterPreset::NeonServerlessPostgres->value) {
            warning('Serverless Postgres clusters get a new hostname when they start. Redeploy connected environments once the cluster is running.');
        }
    }
}
