<?php

namespace App\Commands;

use App\Actions\CreateDatabaseCluster;
use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\Validates;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class DatabaseClusterCreate extends BaseCommand
{
    use DeterminesDefaultRegion;
    use Validates;

    protected $signature = 'database-cluster:create
                            {--name= : Database cluster name}
                            {--type= : Database type}
                            {--region= : Database region}
                            {--json : Output as JSON}';

    protected $description = 'Create a new database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Database Cluster');

        $defaults = array_filter([
            'name' => $this->option('name'),
            'type' => $this->option('type'),
            'region' => $this->option('region') ?: $this->getDefaultRegion(),
        ]);

        $database = $this->loopUntilValid(
            fn () => app(CreateDatabaseCluster::class)->run($this->client, $defaults),
        );

        $this->outputJsonIfWanted($database);

        success('Database cluster created');

        outro("Database cluster created: {$database->name}");
    }
}
