<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
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

        if (! $this->option('force') && ! confirm("Delete key \"{$key->name}\"?", default: false)) {
            error('Delete cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->bucketKeys()->delete($bucket->id, $key->id),
            'Deleting key...',
        );

        success('Bucket key deleted');
    }
}
