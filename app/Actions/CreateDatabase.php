<?php

namespace App\Actions;

use App\Client\Connector;
use App\Dto\Database;
use App\Dto\DatabaseCluster;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CreateDatabase
{
    public function run(Connector $client, DatabaseCluster $cluster, array $defaults = []): Database
    {
        $name = text(
            label: 'Database name',
            placeholder: 'my_database',
            default: $defaults['name'] ?? '',
            required: true,
            validate: fn (string $value) => match (true) {
                ! preg_match('/^[a-z0-9_-]+$/', $value) => 'Must contain only lowercase letters, numbers, hyphens and underscores',
                strlen($value) < 3 => 'Must be at least 3 characters',
                strlen($value) > 40 => 'Must be less than 40 characters',
                default => null,
            },
        );

        return spin(
            fn () => $client->databases()->create($cluster->id, $name),
            'Creating database...',
        );
    }

    public function runWithParams(Connector $client, DatabaseCluster $cluster, string $name): Database
    {
        return spin(
            fn () => $client->databases()->create($cluster->id, $name),
            'Creating database...',
        );
    }
}
