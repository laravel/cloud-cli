<?php

namespace App\Commands;

use App\Client\Requests\UpdateBucketKeyRequestData;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BucketKeyUpdate extends BaseCommand
{
    protected $signature = 'bucket-key:update
                            {bucket? : The bucket ID or name}
                            {key? : The key ID or name}
                            {--name= : Key name}
                            {--permission= : Permission (read_only or read_write)}
                            {--json : Output as JSON}';

    protected $description = 'Update a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        $name = $this->option('name') ? (string) $this->option('name') : null;
        $permission = $this->option('permission') ? (string) $this->option('permission') : null;

        if ($name === null && $permission === null) {
            $this->outputErrorOrThrow('No fields to update. Provide --name or --permission.');

            exit(self::FAILURE);
        }

        $updated = spin(
            fn () => $this->client->bucketKeys()->update(new UpdateBucketKeyRequestData(
                bucketId: $bucket->id,
                keyId: $key->id,
                name: $name,
                permission: $permission,
            )),
            'Updating key...',
        );

        $this->outputJsonIfWanted($updated);

        success('Bucket key updated');
    }
}
