<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
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

        if (! $this->option('force') && ! confirm("Delete bucket \"{$bucket->name}\"?", default: false)) {
            error('Delete cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->objectStorageBuckets()->delete($bucket->id),
            'Deleting bucket...',
        );

        success('Bucket deleted');
    }
}
