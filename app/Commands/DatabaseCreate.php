<?php

namespace App\Commands;

use App\Concerns\CreatesDatabase;

use function Laravel\Prompts\intro;

class DatabaseCreate extends BaseCommand
{
    use CreatesDatabase;

    protected $signature = 'database:create
                            {cluster? : The database cluster ID or name}
                            {--name= : Database (schema) name}
                            {--json : Output as JSON}';

    protected $description = 'Create a new database (schema) in a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Database');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $database = $this->loopUntilValid(fn () => $this->createDatabase($cluster));

        $this->outputJsonIfWanted($database);

        success("Database created: {$database->name}");
    }
}
