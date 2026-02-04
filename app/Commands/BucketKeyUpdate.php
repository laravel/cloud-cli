<?php

namespace App\Commands;

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

        $data = [];

        if ($name = $this->option('name')) {
            $data['name'] = $name;
        }

        if ($permission = $this->option('permission')) {
            $data['permission'] = $permission;
        }

        if (empty($data)) {
            $this->outputErrorOrThrow('No fields to update. Provide --name or --permission.');

            exit(self::FAILURE);
        }

        $updated = spin(
            fn () => $this->client->bucketKeys()->update($bucket->id, $key->id, $data),
            'Updating key...',
        );

        $this->outputJsonIfWanted($updated);

        success('Bucket key updated');
    }
}
