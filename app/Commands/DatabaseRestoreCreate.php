<?php

namespace App\Commands;

use App\Client\Requests\CreateDatabaseRestoreRequestData;
use App\Dto\DatabaseCluster;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class DatabaseRestoreCreate extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseCluster::class;

    protected $signature = 'database-restore:create
                            {cluster? : The database cluster ID or name}
                            {name? : The name of the restore}
                            {--snapshot= : Snapshot ID to restore from}
                            {--point-in-time= : Point-in-time (ISO 8601) to restore to}';

    protected $description = 'Create a database restore from a snapshot or point-in-time';

    protected $aliases = ['db-restore:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Database Restore');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        [$snapshotId, $pointInTime] = $this->resolveRestoreSource($cluster);

        $restored = $this->loopUntilValid(
            fn () => $this->createRestore($cluster, $snapshotId, $pointInTime),
        );

        $this->outputJsonIfWanted($restored);

        success("Database restore created: {$restored->name}");
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolveRestoreSource(DatabaseCluster $cluster): array
    {
        $snapshotId = $this->option('snapshot');
        $pointInTime = $this->option('point-in-time');

        if (! $snapshotId && ! $pointInTime && $this->isInteractive()) {
            $snapshots = spin(
                fn () => $this->client->databaseSnapshots()->list($cluster->id)->collect(),
                'Fetching snapshots...',
            );

            if ($snapshots->isNotEmpty()) {
                $choice = select(
                    label: 'Restore from',
                    options: [
                        'snapshot' => 'Restore from snapshot',
                        'point_in_time' => 'Restore from point-in-time',
                    ],
                );

                if ($choice === 'snapshot') {
                    $snapshotOptions = $snapshots->mapWithKeys(fn ($s) => [$s->id => $s->name])->toArray();
                    $snapshotId = select(
                        label: 'Snapshot',
                        options: $snapshotOptions,
                    );
                } else {
                    $pointInTime = text(
                        label: 'Point-in-time (ISO 8601)',
                        required: true,
                        placeholder: '2024-01-15T12:00:00Z',
                    );
                }
            } else {
                $pointInTime = text(
                    label: 'Point-in-time (ISO 8601)',
                    required: true,
                    placeholder: '2024-01-15T12:00:00Z',
                );
            }
        }

        if (! $snapshotId && ! $pointInTime) {
            $this->outputErrorOrThrow('Provide either --snapshot or --point-in-time.');

            throw new CommandExitException(self::FAILURE);
        }

        return [$snapshotId, $pointInTime];
    }

    protected function createRestore(DatabaseCluster $cluster, ?string $snapshotId, ?string $pointInTime): DatabaseCluster
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Name',
                    default: $value ?? '',
                    required: true,
                ),
            ),
        );

        return spin(
            fn () => $this->client->databaseRestores()->create(
                new CreateDatabaseRestoreRequestData(
                    name: $this->form()->get('name'),
                    clusterId: $cluster->id,
                    databaseSnapshotId: $snapshotId,
                    restoreTime: $pointInTime,
                ),
            ),
            'Creating restore...',
        );
    }
}
