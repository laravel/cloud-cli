<?php

namespace App\Commands;

use App\Client\Requests\UpdateDatabaseUserRequestData;
use App\Concerns\DefinesDatabaseUserAccess;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseUser;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

class DatabaseUserUpdate extends BaseCommand
{
    use DefinesDatabaseUserAccess;

    protected ?string $jsonDataClass = DatabaseUser::class;

    protected $signature = 'database-user:update
                            {cluster? : The database cluster ID or name}
                            {user? : The database user ID or username}
                            {--type= : read_write or read_only}
                            {--database=* : A database ID or name the user may access; repeat for several. Replaces the current access}
                            {--no-databases : Remove access to every database}
                            {--force : Force update without confirmation}';

    protected $description = "Update a database user's permission and database access";

    protected $aliases = ['db-user:update'];

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Database User');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));
        $user = $this->resolvers()->databaseUser()->editable()->from($cluster, $this->argument('user'));

        if ($user->isSuperuser()) {
            $this->failAndExit('The superuser cannot be updated.');
        }

        $this->defineTypeField($user);
        $this->defineDatabasesField($cluster, $user);

        foreach ($this->form()->filled() as $key => $resolver) {
            $this->reportChange(
                $resolver->label(),
                $resolver->previousValue(),
                $key === 'databases'
                    ? $this->databaseNames($this->databasesFromIds($cluster, $this->resolveDatabaseIds($cluster, $resolver->value())))
                    : $resolver->value(),
            );
        }

        $updatedUser = $this->runUpdate(
            fn () => $this->updateUser($cluster, $user),
            fn () => $this->collectDataAndUpdate($cluster, $user),
        );

        $this->outputJsonIfWanted($updatedUser);

        success("Database user updated: {$updatedUser->username}");
    }

    protected function updateUser(DatabaseCluster $cluster, DatabaseUser $user): DatabaseUser
    {
        $databases = $this->form()->get('databases');

        // The API replaces both fields on every update, so anything not being changed is sent as it is now.
        return spin(
            fn () => $this->client->databaseUsers()->update(
                new UpdateDatabaseUserRequestData(
                    clusterId: $cluster->id,
                    userId: $user->id,
                    type: $this->form()->get('type') ?? $user->type->value,
                    databases: $databases === null
                        ? collect($user->databases)->pluck('id')->all()
                        : $this->resolveDatabaseIds($cluster, $databases),
                ),
            ),
            'Updating database user...',
        );
    }

    protected function collectDataAndUpdate(DatabaseCluster $cluster, DatabaseUser $user): DatabaseUser
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())
                ->mapWithKeys(fn ($field) => [$field->key => $field->label()])
                ->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $field) {
            $this->form()->prompt($field);
        }

        return $this->updateUser($cluster, $user);
    }
}
