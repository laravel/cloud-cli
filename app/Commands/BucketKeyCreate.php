<?php

namespace App\Commands;

use App\Client\Requests\CreateBucketKeyRequestData;
use App\Dto\BucketKey;
use App\Dto\ObjectStorageBucket;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketKeyCreate extends BaseCommand
{
    protected ?string $jsonDataClass = BucketKey::class;

    protected $signature = 'bucket-key:create
                            {bucket? : The bucket ID or name}
                            {--name= : Key name}
                            {--permission= : Permission (read_only or read_write)}';

    protected $description = 'Create a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $key = $this->loopUntilValid(fn () => $this->createBucketKey($bucket));

        $this->outputJsonIfWanted($key);

        success("Bucket key created: {$key->name}");
    }

    protected function createBucketKey(ObjectStorageBucket $bucket)
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Key name',
                    default: $value ?? '',
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'permission',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Permission',
                    options: ['read_only' => 'Read only', 'read_write' => 'Read and write'],
                    default: $value ?? 'read_write',
                    required: true,
                ))
                ->nonInteractively(fn () => 'read_write'),
        );

        return spin(
            fn () => $this->client->bucketKeys()->create(
                new CreateBucketKeyRequestData(
                    filesystemId: $bucket->id,
                    name: $this->form()->get('name'),
                    permission: $this->form()->get('permission'),
                ),
            ),
            'Creating key...',
        );
    }
}
