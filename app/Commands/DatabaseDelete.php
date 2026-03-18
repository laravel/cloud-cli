<?php

namespace App\Commands;

use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseDelete extends BaseCommand
{
    protected $signature = 'database:delete
                            {cluster? : The database cluster ID or name}
                            {database? : The database (schema) ID or name}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Delete a database (schema) from a database cluster';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Database');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $database = $this->resolvers()
            ->database()
            ->withCluster($cluster)
            ->from($this->argument('database'));

        if (! $this->option('force') && ! confirm("Delete database '{$database->name}' and detach from associated environments?")) {
            error('Cancelled');

            return self::SUCCESS;
        }

        try {
            spin(
                fn () => $this->client->databases()->delete($cluster->id, $database->id),
                'Deleting database...',
            );

            $this->outputJsonIfWanted('Database deleted.');

            success('Database deleted.');

            return self::SUCCESS;
        } catch (RequestException $e) {
            error('Failed to delete database: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
