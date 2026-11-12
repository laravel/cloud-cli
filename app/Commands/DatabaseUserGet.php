<?php

namespace App\Commands;

use App\Dto\DatabaseUser;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseUserGet extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseUser::class;

    protected $signature = 'database-user:get
                            {cluster? : The database cluster ID or name}
                            {user? : The database user ID or username}';

    protected $description = 'Get database user details';

    protected $aliases = ['db-user:get'];

    public function handle()
    {
        $this->ensureClient();

        intro('Database User Details');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $user = $this->resolvers()->databaseUser()->from($cluster, $this->argument('user'));

        $user = spin(
            fn () => $this->client->databaseUsers()->get($cluster->id, $user->id),
            'Fetching database user...',
        );

        $this->outputJsonIfWanted($user);

        dataList([
            'ID' => $user->id,
            'Username' => $user->username,
            'Permission' => $user->type->label(),
            'Status' => $user->status->value,
            'Databases' => collect($user->databases)->pluck('name')->join(', ') ?: '—',
            'Created At' => $user->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
