<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketKeyDelete extends BaseCommand
{
    protected $signature = 'bucket-key:delete
                            {key? : The key ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->resolve();
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        if (! $this->option('force') && ! confirm("Delete key '{$key->name}'?", default: false)) {
            error('Cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->bucketKeys()->delete($key->id),
            'Deleting key...',
        );

        $this->outputJsonIfWanted('Bucket key deleted.');

        success('Bucket key deleted');
    }
}
