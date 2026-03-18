<?php

namespace App\Commands;

use App\Dto\ObjectStorageBucket;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketList extends BaseCommand
{
    protected $signature = 'bucket:list
                            {--json : Output as JSON}
                            {--type= : Filter by type}
                            {--status= : Filter by status}
                            {--visibility= : Filter by visibility}';

    protected $description = 'List object storage buckets';

    public function handle()
    {
        $this->ensureClient();

        intro('Object Storage Buckets');

        answered('Organization', $this->client->meta()->organization()->name);

        $buckets = spin(
            fn () => $this->client->objectStorageBuckets()->list(
                $this->option('type'),
                $this->option('status'),
                $this->option('visibility'),
            )->collect(),
            'Fetching buckets...',
        );

        if ($buckets->isEmpty()) {
            $this->failAndExit('No buckets found.');
        }

        $this->outputJsonIfWanted($buckets->toArray());

        dataTable(
            headers: ['ID', 'Name', 'Type', 'Status', 'Visibility', 'Region'],
            rows: $buckets->map(fn (ObjectStorageBucket $b) => [
                $b->id,
                $b->name,
                $b->type->value ?? $b->type->name ?? '—',
                $b->status->value ?? $b->status->name ?? '—',
                $b->visibility->value ?? $b->visibility->name ?? '—',
                '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('bucket:get', ['bucket' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
