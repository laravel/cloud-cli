<?php

namespace App\Commands;

use App\Client\Requests\CreateDatabaseUserRequestData;
use App\Concerns\DefinesDatabaseUserAccess;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseUser;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DatabaseUserCreate extends BaseCommand
{
    use DefinesDatabaseUserAccess;

    protected ?string $jsonDataClass = DatabaseUser::class;

    protected $signature = 'database-user:create
                            {cluster? : The database cluster ID or name}
                            {--username= : Lowercase letters, numbers, and underscores, starting with a letter or underscore}
                            {--type= : read_write or read_only (default: read_write)}
                            {--database=* : A database ID or name the user may access; repeat for several, omit for none}';

    protected $description = 'Create a database user (its password is shown only once)';

    protected $aliases = ['db-user:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Database User');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $user = $this->loopUntilValid(fn () => $this->createUser($cluster));

        if ($this->wantsJson() && ! $this->option('show-sensitive')) {
            $this->outputWarning('The password is masked and cannot be retrieved again. Pass --show-sensitive when creating a user to include it.');
        }

        $this->outputJsonIfWanted($user);

        success("Database user created: {$user->username}");

        // --show-sensitive only governs JSON. Here a person is reading, and this is the only time the API returns the password.
        dataList([
            'ID' => $user->id,
            'Username' => $user->username,
            'Password' => $user->password ?? '—',
            'Permission' => $user->type->label(),
            'Databases' => $this->databaseNames($user->databases),
        ]);

        warning('Save this password now. It will not be shown again.');
    }

    protected function createUser(DatabaseCluster $cluster): DatabaseUser
    {
        $this->form()->prompt(
            'username',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Username',
                    default: $value ?? '',
                    required: true,
                    validate: fn (string $username) => match (true) {
                        strlen($username) < 3 => 'The username must have at least 3 characters.',
                        ! preg_match('/^[a-z_][a-z0-9_]*$/', $username) => 'The username must start with a lowercase letter or underscore and may only contain lowercase letters, numbers, and underscores.',
                        default => null,
                    },
                ),
            ),
        );

        $this->defineTypeField();
        $this->form()->prompt('type');

        $this->defineDatabasesField($cluster);
        $this->form()->prompt('databases');

        return spin(
            fn () => $this->client->databaseUsers()->create(
                new CreateDatabaseUserRequestData(
                    clusterId: $cluster->id,
                    username: $this->form()->get('username'),
                    type: $this->form()->get('type'),
                    databases: $this->resolveDatabaseIds($cluster, $this->form()->get('databases')),
                ),
            ),
            'Creating database user...',
        );
    }
}
