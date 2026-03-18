<?php

namespace App\Commands;

use App\Dto\BucketKey;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketKeyList extends BaseCommand
{
    protected $signature = 'bucket-key:list
                            {bucket? : The bucket ID or name}
                            {--json : Output as JSON}';

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

        if ($items->isEmpty()) {
            $this->failAndExit('No keys found.');
        }

        $this->outputJsonIfWanted($items->toArray());

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
