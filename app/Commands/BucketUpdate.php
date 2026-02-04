<?php

namespace App\Commands;

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

        $data = [];

        if ($name = $this->option('name')) {
            $data['name'] = $name;
        }

        if ($visibility = $this->option('visibility')) {
            $data['visibility'] = $visibility;
        }

        if (empty($data)) {
            $this->outputErrorOrThrow('No fields to update. Provide at least one option (--name or --visibility).');

            exit(self::FAILURE);
        }

        $updated = spin(
            fn () => $this->client->objectStorageBuckets()->update($bucket->id, $data),
            'Updating bucket...',
        );

        $this->outputJsonIfWanted($updated);

        success('Bucket updated');
    }
}
