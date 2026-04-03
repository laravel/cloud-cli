<?php

namespace App\Commands;

use App\Concerns\CreatesDatabase;
use App\Dto\Database;

use function Laravel\Prompts\intro;

class DatabaseCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Database::class;

    use CreatesDatabase;

    protected $signature = 'database:create
                            {cluster? : The database cluster ID or name}
                            {--name= : Database (schema) name}';

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
