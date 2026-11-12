<?php

namespace App\Resolvers;

use App\Dto\DatabaseCluster;
use App\Dto\DatabaseUser;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class DatabaseUserResolver extends Resolver
{
    protected bool $editableOnly = false;

    /**
     * Cloud refuses to update or delete the superuser, so don't offer it when picking one.
     */
    public function editable(): static
    {
        $this->editableOnly = true;

        return $this;
    }

    public function from(DatabaseCluster $cluster, ?string $userIdOrUsername = null): ?DatabaseUser
    {
        $user = ($userIdOrUsername ? $this->fromIdentifier($cluster, $userIdOrUsername) : null)
            ?? $this->fromInput($cluster);

        if (! $user) {
            $this->failAndExit('Unable to resolve database user: '.($userIdOrUsername ?? 'Provide a valid user ID or username.').". Run `cloud database-user:list {$cluster->id} --json` to see available users.");
        }

        $this->displayResolved('User', $user->username, $user->id);

        return $user;
    }

    public function fromIdentifier(DatabaseCluster $cluster, string $identifier): ?DatabaseUser
    {
        $users = $this->fetchAll($cluster);

        return $users->firstWhere('id', $identifier)
            ?? $users->firstWhere('username', $identifier);
    }

    public function fromInput(DatabaseCluster $cluster): ?DatabaseUser
    {
        $users = $this->fetchAll($cluster)
            ->when($this->editableOnly, fn (LazyCollection $users) => $users->reject(fn (DatabaseUser $user) => $user->isSuperuser()));

        if ($users->isEmpty()) {
            $this->failAndExit($this->editableOnly
                ? 'No users other than the superuser found for this database cluster.'
                : 'No users found for this database cluster.');
        }

        if ($users->hasSole()) {
            return $users->first();
        }

        $options = $users->mapWithKeys(fn (DatabaseUser $u) => [$u->id => $u->username])->toArray();

        $this->ensureInteractive('Multiple database users found. Provide a user ID or username.', ['options' => $options]);

        $selected = select(
            label: 'User',
            options: $options,
            info: fn ($id) => $id,
        );

        $this->displayResolved = false;

        return $users->firstWhere('id', $selected);
    }

    protected function fetchAll(DatabaseCluster $cluster): LazyCollection
    {
        return spin(
            fn () => $this->client->databaseUsers()->list($cluster->id)->collect(),
            'Fetching database users...',
        );
    }

    protected function idPrefix(): string
    {
        return 'dbu-';
    }
}
