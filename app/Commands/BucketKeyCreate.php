<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketKeyCreate extends BaseCommand
{
    protected $signature = 'bucket-key:create
                            {bucket? : The bucket ID or name}
                            {--name= : Key name}
                            {--permission= : Permission (read_only or read_write)}
                            {--json : Output as JSON}';

    protected $description = 'Create a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $name = $this->option('name') ?? text(
            label: 'Key name',
            required: true,
        );

        $permission = $this->option('permission') ?? select(
            label: 'Permission',
            options: ['read_only' => 'Read only', 'read_write' => 'Read and write'],
            default: 'read_write',
        );

        $key = spin(
            fn () => $this->client->bucketKeys()->create($bucket->id, $name, $permission),
            'Creating key...',
        );

        $this->outputJsonIfWanted($key);

        success('Bucket key created');

        outro("Key created: {$key->name}");
    }
}
