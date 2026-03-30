<?php

namespace App\Concerns;

use App\Client\Requests\CreateDatabaseRequestData;
use App\Dto\Database;
use App\Dto\DatabaseCluster;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

trait CreatesDatabase
{
    protected function createDatabase(DatabaseCluster $cluster): Database
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Database name',
                    default: $value ?? '',
                    required: true,
                    validate: fn (string $v) => match (true) {
                        ! preg_match('/^[a-z0-9_-]+$/', $v) => 'Must contain only lowercase letters, numbers, hyphens and underscores',
                        strlen($v) < 3 => 'Must be at least 3 characters',
                        strlen($v) > 40 => 'Must be less than 40 characters',
                        default => null,
                    },
                ),
            ),
        );

        return spin(
            fn () => $this->client->databases()->create(
                new CreateDatabaseRequestData(
                    clusterId: $cluster->id,
                    name: $this->form()->get('name'),
                ),
            ),
            'Creating database...',
        );
    }

    protected function createDatabaseWithName(DatabaseCluster $cluster, string $name): Database
    {
        return spin(
            fn () => $this->client->databases()->create(
                new CreateDatabaseRequestData(
                    clusterId: $cluster->id,
                    name: $name,
                ),
            ),
            'Creating database...',
        );
    }
}
