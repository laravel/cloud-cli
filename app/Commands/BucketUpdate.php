<?php

namespace App\Commands;

use App\Client\Requests\UpdateObjectStorageBucketRequestData;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketUpdate extends BaseCommand
{
    protected $signature = 'bucket:update
                            {bucket? : The bucket ID or name}
                            {--name= : Bucket name}
                            {--visibility= : Visibility (private or public)}
                            {--json : Output as JSON}';

    protected $description = 'Update an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Bucket');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $name = $this->option('name') ? (string) $this->option('name') : null;
        $visibility = $this->option('visibility') ? (string) $this->option('visibility') : null;

        if ($name === null && $visibility === null) {
            $this->outputErrorOrThrow('No fields to update. Provide at least one option (--name or --visibility).');

            exit(self::FAILURE);
        }

        $updated = spin(
            fn () => $this->client->objectStorageBuckets()->update(new UpdateObjectStorageBucketRequestData(
                bucketId: $bucket->id,
                name: $name,
                visibility: $visibility,
            )),
            'Updating bucket...',
        );

        $this->outputJsonIfWanted($updated);

        success('Bucket updated');
    }
}
