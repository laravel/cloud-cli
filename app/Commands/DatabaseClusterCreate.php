<?php

namespace App\Commands;

use App\Concerns\CreatesDatabaseCluster;
use App\Concerns\DeterminesDefaultRegion;
use App\Dto\DatabaseCluster;

use function Laravel\Prompts\intro;

class DatabaseClusterCreate extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    use CreatesDatabaseCluster;
    use DeterminesDefaultRegion;

    protected $signature = 'database-cluster:create
                            {--name= : Database cluster name}
                            {--type= : Database type (run `cloud database:types` for options)}
                            {--engine-version= : Database engine version (e.g. 18, 8.4). Default: newest available}
                            {--region= : Database region}
                            {--cluster= : Dedicated cluster ID to create the database cluster on}
                            {--create-default-database= : Whether to create a default database (true or false; Neon clusters cannot skip it). Default: true}';

    protected $description = 'Create a new database cluster';

    protected $aliases = ['db-cluster:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Create Database Cluster');

        $database = $this->loopUntilValid(
            fn () => $this->createDatabaseCluster(),
        );

        $this->outputJsonIfWanted($database);

        success("Database cluster created: {$database->name}");
    }
}
