<?php

namespace App\Commands;

use App\Client\Requests\CreateObjectStorageBucketRequestData;
use App\Concerns\DeterminesDefaultRegion;
use App\Dto\ObjectStorageBucket;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketCreate extends BaseCommand
{
    protected ?string $jsonDataClass = ObjectStorageBucket::class;

    use DeterminesDefaultRegion;

    protected $signature = 'bucket:create
                            {--name= : Bucket name}
                            {--region= : Region}
                            {--visibility= : Visibility (private or public)}
                            {--jurisdiction= : Jurisdiction (eu or default)}
                            {--key-name= : Key name (required for S3 compatible buckets)}
                            {--key-permission= : Key permission (read_only or read_write)}
                            {--allowed-origins= : Allowed origins (comma-separated list)}';

    protected $description = 'Create an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Bucket');

        $bucket = $this->loopUntilValid($this->createBucket(...));

        $this->outputJsonIfWanted($bucket);

        success('Bucket created');
    }

    protected function createBucket()
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Bucket name',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($v) => strlen($v) < 3 ? 'Name must be at least 3 characters' : null,
                ),
            ),
        );

        $this->form()->prompt(
            'visibility',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Visibility',
                    options: ['private' => 'Private', 'public' => 'Public'],
                    default: $value ?? 'private',
                    required: true,
                ))
                ->nonInteractively(fn () => 'private'),
        );

        $this->form()->prompt(
            'jurisdiction',
            fn ($resolver) => $resolver
                ->fromInput(fn ($value) => confirm(
                    'Do you want to store data in the EU?',
                    default: $value ?? false,
                ) ? 'eu' : 'default')
                ->nonInteractively(fn () => 'default'),
        );

        $this->form()->prompt(
            'key_name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Key name',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($v) => match (true) {
                        strlen($v) < 3 => 'Name must be at least 3 characters',
                        strlen($v) > 40 => 'Name must be less than 40 characters',
                        default => null,
                    },
                ),
            ),
        );

        $this->form()->prompt(
            'key_permission',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Key permission',
                    options: ['read_only' => 'Read only', 'read_write' => 'Read and write'],
                    default: $value ?? 'read_write',
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'allowed_origins',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Allowed origins',
                    default: $value ?? '',
                    hint: 'Comma-separated list of origins',
                ),
            ),
        );

        $allowedOrigins = $this->form()->get('allowed_origins');

        return spin(
            fn () => $this->client->objectStorageBuckets()->create(
                new CreateObjectStorageBucketRequestData(
                    name: $this->form()->get('name'),
                    visibility: $this->form()->get('visibility'),
                    jurisdiction: $this->form()->get('jurisdiction'),
                    keyName: $this->form()->get('key_name'),
                    keyPermission: $this->form()->get('key_permission'),
                    allowedOrigins: $allowedOrigins ? explode(',', $allowedOrigins) : null,
                ),
            ),
            'Creating bucket...',
        );
    }
}
