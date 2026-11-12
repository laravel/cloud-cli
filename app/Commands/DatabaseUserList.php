<?php

namespace App\Commands;

use App\Dto\DatabaseUser;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class DatabaseUserList extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseUser::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'database-user:list
                            {cluster? : The database cluster ID or name}';

    protected $description = 'List the users of a database cluster';

    protected $aliases = ['db-user:list'];

    public function handle()
    {
        $this->ensureClient();

        intro('Database Users');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $users = $this->fetchList(
            fn () => $this->client->databaseUsers()->list($cluster->id),
            'Fetching database users...',
        );

        $this->outputJsonIfWanted($users->toArray());

        if ($users->isEmpty()) {
            warning('No database users found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Username', 'Permission', 'Status', 'Databases'],
            rows: $users->map(fn (DatabaseUser $user) => [
                $user->id,
                $user->username,
                $user->type->label(),
                $user->status->value,
                collect($user->databases)->pluck('name')->join(', ') ?: '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('database-user:get', [
                        'cluster' => $cluster->id,
                        'user' => $row[0],
                    ]),
                    'View',
                ],
            ],
        );
    }
}
