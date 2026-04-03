<?php

namespace App\Commands;

use App\Dto\ObjectStorageBucket;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class BucketList extends BaseCommand
{
    protected ?string $jsonDataClass = ObjectStorageBucket::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'bucket:list
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

        $this->outputJsonIfWanted($buckets->toArray());

        if ($buckets->isEmpty()) {
            warning('No buckets found.');

            return self::FAILURE;
        }

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
