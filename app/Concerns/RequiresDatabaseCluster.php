<?php

namespace App\Concerns;

use App\Dto\DatabaseCluster;
use Exception;
use Illuminate\Support\Collection;
use RuntimeException;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

trait RequiresDatabaseCluster
{
    /**
     * @param  Collection<DatabaseCluster>  $databases
     */
    protected function getDatabaseCluster(?Collection $databases = null, $showPrompt = true): DatabaseCluster
    {
        if ($this->argument('database')) {
            $identifier = $this->argument('database');

            if ($databases) {
                $database = $this->getByNameOrId($identifier, $databases);
            } else {
                if (str_starts_with($identifier, 'db-')) {
                    try {
                        $database = spin(
                            fn () => $this->client->getDatabase($identifier),
                            'Fetching database...'
                        );
                    } catch (Exception $e) {
                        $database = $this->getByNameOrId($identifier);
                    }
                } else {
                    $database = $this->getByNameOrId($identifier);
                }
            }

            if (! $database) {
                throw new RuntimeException("Database '{$identifier}' not found.");
            }

            $this->displayDatabase($database, $showPrompt);

            return $database;
        }

        $databases ??= $this->fetchDatabases();

        if ($databases->containsOneItem()) {
            $database = $databases->first();

            $this->displayDatabase($database, $showPrompt);

            return $database;
        }

        $selectedDatabase = select(
            label: 'Database',
            options: $databases->mapWithKeys(fn ($database) => [$database->id => $database->name]),
        );

        return $databases->firstWhere('id', $selectedDatabase);
    }

    protected function getByNameOrId(string $identifier, ?Collection $databases = null): ?DatabaseCluster
    {
        $databases ??= $this->fetchDatabases();

        return $databases->firstWhere('id', $identifier)
            ?? $databases->firstWhere('name', $identifier);
    }

    protected function displayDatabase(DatabaseCluster $database, $showPrompt = true): void
    {
        if ($showPrompt) {
            answered(label: 'Database', answer: "{$database->name}");
        }
    }

    protected function fetchDatabases(): Collection
    {
        return collect(spin(
            fn () => $this->client->listDatabases(),
            'Fetching databases...'
        )->data);
    }
}
