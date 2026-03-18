<?php

namespace App\Resolvers;

use App\Dto\DatabaseCluster;
use App\Dto\DatabaseSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\spin;

class DatabaseSnapshotResolver extends Resolver
{
    public function from(DatabaseCluster $cluster, ?string $snapshotIdOrName = null): ?DatabaseSnapshot
    {
        $snapshot = ($snapshotIdOrName ? $this->fromIdentifier($cluster, $snapshotIdOrName) : null)
            ?? $this->fromInput($cluster);

        if (! $snapshot) {
            if ($snapshotIdOrName === null) {
                $this->failAndExit('No snapshot could be resolved. Provide a valid snapshot ID or name as an argument.');
            } elseif ($this->looksLikeId($snapshotIdOrName)) {
                $this->failAndExit("Snapshot '{$snapshotIdOrName}' not found. Verify the ID is correct and belongs to this database cluster.");
            } else {
                $this->failAndExit("No snapshot named '{$snapshotIdOrName}' found for this database cluster.");
            }
        }

        $this->displayResolved('Snapshot', $snapshot->name, $snapshot->id);

        return $snapshot;
    }

    public function fromIdentifier(DatabaseCluster $cluster, string $identifier): ?DatabaseSnapshot
    {
        $snapshots = $this->fetchAll($cluster);

        return $snapshots->firstWhere('id', $identifier)
            ?? $snapshots->firstWhere('name', $identifier);
    }

    public function fromInput(DatabaseCluster $cluster): ?DatabaseSnapshot
    {
        $snapshots = $this->fetchAll($cluster);

        if ($snapshots->isEmpty()) {
            $this->failAndExit('No snapshots found for this database cluster.');
        }

        if ($snapshots->hasSole()) {
            return $snapshots->first();
        }

        $this->ensureInteractive('Please provide a snapshot ID or name.');

        $selected = selectWithContext(
            label: 'Snapshot',
            options: $snapshots->mapWithKeys(fn (DatabaseSnapshot $s) => [$s->id => $s->name])->toArray(),
        );

        $this->displayResolved = false;

        return $snapshots->firstWhere('id', $selected);
    }

    protected function fetchAll(DatabaseCluster $cluster): Collection|LazyCollection
    {
        return spin(
            fn () => $this->client->databaseSnapshots()->list($cluster->id)->collect(),
            'Fetching snapshots...',
        );
    }

    protected function idPrefix(): string
    {
        return 'snap-';
    }
}
