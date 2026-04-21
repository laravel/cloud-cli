<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketKeyDelete extends BaseCommand
{
    protected $signature = 'bucket-key:delete
                            {bucket? : The bucket ID or name}
                            {key? : The key ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        $this->confirmDestructive("Delete key '{$key->name}'?");

        spin(
            fn () => $this->client->bucketKeys()->delete($key->id),
            'Deleting key...',
        );

        $this->outputJsonIfWanted('Bucket key deleted.');

        success('Bucket key deleted');
    }
}
