<?php

namespace App\Commands;

use App\Dto\BucketKey;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class BucketKeyList extends BaseCommand
{
    protected ?string $jsonDataClass = BucketKey::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'bucket-key:list
                            {bucket? : The bucket ID or name}';

    protected $description = 'List keys for an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Bucket Keys');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $keys = spin(
            fn () => $this->client->bucketKeys()->list($bucket->id)->collect(),
            'Fetching keys...',
        );

        $items = collect($keys);

        $this->outputJsonIfWanted($items->toArray());

        if ($items->isEmpty()) {
            warning('No keys found.');

            return self::FAILURE;
        }

        dataTable(
            headers: ['ID', 'Name', 'Permission', 'Created At'],
            rows: $items->map(fn (BucketKey $k) => [
                $k->id,
                $k->name,
                $k->permission,
                $k->createdAt?->toIso8601String() ?? '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('bucket-key:get', [
                        'bucket' => $bucket->id,
                        'key' => $row[0],
                    ]),
                    'View',
                ],
            ],
        );
    }
}
