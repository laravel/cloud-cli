<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseUserDelete extends BaseCommand
{
    protected $signature = 'database-user:delete
                            {cluster? : The database cluster ID or name}
                            {user? : The database user ID or username}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a database user';

    protected $aliases = ['db-user:delete'];

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Database User');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $user = $this->resolvers()->databaseUser()->editable()->from($cluster, $this->argument('user'));

        if ($user->isSuperuser()) {
            $this->failAndExit('The superuser cannot be deleted.');
        }

        $this->confirmDestructive("Delete database user '{$user->username}'? Anything connecting as this user will lose access.");

        spin(
            fn () => $this->client->databaseUsers()->delete($cluster->id, $user->id),
            'Deleting database user...',
        );

        $this->outputJsonIfWanted('Database user deleted.');

        success('Database user deleted');
    }
}
