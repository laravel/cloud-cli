<?php

namespace App\Commands;

use App\Client\Requests\CreateDatabaseSnapshotRequestData;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseSnapshot;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class DatabaseSnapshotCreate extends BaseCommand
{
    protected ?string $jsonDataClass = DatabaseSnapshot::class;

    protected $signature = 'database-snapshot:create
                            {cluster? : The database cluster ID or name}';

    protected $description = 'Create a database snapshot';

    protected $aliases = ['db-snapshot:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Database Snapshot');

        $cluster = $this->resolvers()->databaseCluster()->from($this->argument('cluster'));

        $snapshot = $this->loopUntilValid(fn () => $this->createSnapshot($cluster));

        $this->outputJsonIfWanted($snapshot);

        success('Database snapshot created');
    }

    protected function createSnapshot(DatabaseCluster $cluster)
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

        $this->form()->prompt(
            'description',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => textarea(
                    label: 'Description',
                    default: $value ?? '',
                ),
            ),
        );

        return spin(
            fn () => $this->client->databaseSnapshots()->create(
                new CreateDatabaseSnapshotRequestData(
                    $cluster->id,
                    $this->form()->get('name'),
                    $this->form()->get('description'),
                ),
            ),
            'Creating snapshot...',
        );
    }
}
