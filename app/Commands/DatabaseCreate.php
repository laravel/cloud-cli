<?php

namespace App\Commands;

use App\Actions\CreateDatabase;
use App\Concerns\Validates;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class DatabaseCreate extends BaseCommand
{
    use Validates;

    protected $signature = 'database:create
                            {database-cluster? : The database cluster ID or name}
                            {--name= : Database (schema) name}
                            {--json : Output as JSON}';

    protected $description = 'Create a new database (schema) in a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Database');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('database-cluster'));

        $defaults = array_filter([
            'name' => $this->option('name'),
        ]);

        if (empty($defaults['name']) && ! $this->isInteractive()) {
            $this->failAndExit('Provide --name when non-interactive.');
        }

        $creator = app(CreateDatabase::class);

        $database = $this->loopUntilValid(
            fn () => $this->option('name') && ! $this->isInteractive()
                ? $creator->runWithParams($this->client, $cluster, $this->option('name'))
                : $creator->run($this->client, $cluster, $defaults),
        );

        $this->outputJsonIfWanted($database);

        success('Database created');

        outro("Database created: {$database->name}");
    }
}
