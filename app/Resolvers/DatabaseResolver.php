<?php

namespace App\Resolvers;

use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Resolvers\Concerns\HasDatabaseCluster;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\spin;

class DatabaseResolver extends Resolver
{
    use HasDatabaseCluster;

    public function resolve(): ?Database
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?Database
    {
        $database = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $database) {
            if ($idOrName === null) {
                $this->failAndExit('No database could be resolved. Provide a valid database ID or name as an argument.');
            } elseif ($this->looksLikeId($idOrName)) {
                $this->failAndExit("Database '{$idOrName}' not found. Verify the ID is correct and belongs to this cluster.");
            } else {
                $this->failAndExit("No database named '{$idOrName}' found in this cluster.");
            }
        }

        $this->displayResolved('Database', $database->name, $database->id);

        return $database;
    }

    public function fromIdentifier(string $identifier): ?Database
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->databases()->get($this->cluster()->id, $identifier),
                'Fetching database...',
            ),
            fn () => $this->resolveFromCluster($identifier),
        );
    }

    protected function resolveFromCluster(string $identifier): ?Database
    {
        $databases = $this->fetchAll($this->cluster());

        return $databases->firstWhere('id', $identifier)
            ?? $databases->firstWhere('name', $identifier);
    }

    public function fromInput(): ?Database
    {
        $databases = $this->fetchAll($this->cluster());

        if ($databases->isEmpty()) {
            $this->failAndExit('No databases found for this cluster.');
        }

        if ($databases->hasSole()) {
            return $databases->first();
        }

        $this->ensureInteractive('Please provide a database ID or name.');

        $selected = selectWithContext(
            label: 'Database',
            options: $databases->mapWithKeys(fn (Database $d) => [$d->id => $d->name])->toArray(),
        );

        $this->displayResolved = false;

        return $databases->firstWhere('id', $selected);
    }

    protected function fetchAll(DatabaseCluster $cluster): LazyCollection
    {
        return spin(
            fn () => $this->client->databases()->list($cluster->id)->collect(),
            'Fetching databases...',
        );
    }

    protected function idPrefix(): string|callable
    {
        return fn ($identifier) => is_numeric($identifier);
    }
}
