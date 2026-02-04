<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketKeyGet extends BaseCommand
{
    protected $signature = 'bucket-key:get
                            {bucket? : The bucket ID or name}
                            {key? : The key ID or name}
                            {--json : Output as JSON}';

    protected $description = 'Get bucket key details';

    public function handle()
    {
        $this->ensureClient();

        intro('Bucket Key Details');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        $key = spin(
            fn () => $this->client->bucketKeys()->get($bucket->id, $key->id),
            'Fetching key...',
        );

        $this->outputJsonIfWanted($key);

        dataList([
            'ID' => $key->id,
            'Name' => $key->name,
            'Permission' => $key->permission,
            'Created At' => $key->createdAt?->toIso8601String() ?? '-',
        ]);
    }
}
