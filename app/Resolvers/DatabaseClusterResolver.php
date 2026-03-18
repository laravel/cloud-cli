<?php

namespace App\Resolvers;

use App\Dto\DatabaseCluster;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;

class DatabaseClusterResolver extends Resolver
{
    public function resolve(): ?DatabaseCluster
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?DatabaseCluster
    {
        $database = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $database) {
            if ($idOrName === null) {
                $this->failAndExit('No database cluster could be resolved. Provide a valid database cluster ID or name as an argument.');
            } elseif ($this->looksLikeId($idOrName)) {
                $this->failAndExit("Database cluster '{$idOrName}' not found. Verify the ID is correct and belongs to your organization.");
            } else {
                $this->failAndExit("No database cluster named '{$idOrName}' found in your organization.");
            }
        }

        $this->displayResolved('Database', $database->name, $database->id);

        return $database;
    }

    public function fromIdentifier(string $identifier): ?DatabaseCluster
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->databaseClusters()->include('schemas')->get($identifier),
                'Fetching database...',
            ),
            fn () => $this->fetchAndFind($identifier),
        );
    }

    public function fromInput(): ?DatabaseCluster
    {
        $databases = $this->fetchAll();

        if ($databases->isEmpty()) {
            $this->failAndExit('No database clusters found.');
        }

        if ($databases->hasSole()) {
            return $databases->first();
        }

        $this->ensureInteractive('Please provide a database cluster ID or name.');

        $selectedDatabase = selectWithContext(
            label: 'Database',
            options: $databases->mapWithKeys(fn ($database) => [$database->id => $database->name])->toArray(),
        );

        $this->displayResolved = false;

        return $databases->firstWhere('id', $selectedDatabase);
    }

    public function fromCollection(Collection $databases, string $identifier): ?DatabaseCluster
    {
        return $databases->firstWhere('id', $identifier)
            ?? $databases->firstWhere('name', $identifier);
    }

    public function fetchAndFind(string $identifier): ?DatabaseCluster
    {
        return $this->fromCollection($this->fetchAll(), $identifier);
    }

    protected function fetchAll(): Collection
    {
        return collect(spin(
            fn () => $this->client->databaseClusters()->include('schemas')->list()->items(),
            'Fetching databases...',
        ));
    }

    protected function idPrefix(): string
    {
        return 'db-';
    }
}
