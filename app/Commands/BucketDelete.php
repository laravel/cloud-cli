<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketDelete extends BaseCommand
{
    protected $signature = 'bucket:delete
                            {bucket? : The bucket ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Bucket');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $this->confirmDestructive("Delete bucket '{$bucket->name}' and keys?");

        $keys = spin(
            fn () => $this->client->bucketKeys()->list($bucket->id)->collect(),
            'Fetching keys...',
        );

        foreach ($keys as $key) {
            spin(
                fn () => $this->client->bucketKeys()->delete($key->id),
                "Deleting key \"{$key->name}\"...",
            );
        }

        spin(
            fn () => $this->client->objectStorageBuckets()->delete($bucket->id),
            'Deleting bucket...',
        );

        $this->outputJsonIfWanted('Bucket deleted.');

        success('Bucket deleted');
    }
}
