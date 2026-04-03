<?php

namespace App\Commands;

use App\Client\Requests\UpdateBucketKeyRequestData;
use App\Dto\BucketKey;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketKeyUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = BucketKey::class;

    protected $signature = 'bucket-key:update
                            {key? : The key ID or name}
                            {--name= : Key name}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->resolve();
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        $this->defineFields($key);

        foreach ($this->form()->filled() as $fieldKey => $resolver) {
            $this->reportChange(
                $resolver->label(),
                $resolver->previousValue(),
                $resolver->value(),
            );
        }

        $updatedKey = $this->runUpdate(
            fn () => $this->updateKey($key),
            fn () => $this->collectDataAndUpdate($key),
        );

        $this->outputJsonIfWanted($updatedKey);

        success("Bucket key updated: {$updatedKey->name}");
    }

    protected function updateKey(BucketKey $key): BucketKey
    {
        spin(
            fn () => $this->client->bucketKeys()->update(
                new UpdateBucketKeyRequestData(
                    filesystemKey: $key->id,
                    name: $this->form()->get('name'),
                ),
            ),
            'Updating key...',
        );

        return $this->client->bucketKeys()->get($key->id);
    }

    protected function defineFields(BucketKey $key): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    required: true,
                    default: $value ?? $key->name,
                ),
            ),
        )->setPreviousValue($key->name);
    }

    protected function collectDataAndUpdate(BucketKey $key): BucketKey
    {
        $this->form()->prompt('name');

        return $this->updateKey($key);
    }
}
