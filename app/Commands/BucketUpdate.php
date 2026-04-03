<?php

namespace App\Commands;

use App\Client\Requests\UpdateObjectStorageBucketRequestData;
use App\Dto\ObjectStorageBucket;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = ObjectStorageBucket::class;

    protected $signature = 'bucket:update
                            {bucket? : The bucket ID or name}
                            {--name= : Bucket name}
                            {--visibility= : Visibility (private or public)}
                            {--force : Force update without confirmation}';

    protected $description = 'Update an object storage bucket';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Bucket');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));

        $this->defineFields($bucket);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedBucket = $this->runUpdate(
            fn () => $this->updateBucket($bucket),
            fn () => $this->collectDataAndUpdate($bucket),
        );

        $this->outputJsonIfWanted($updatedBucket);

        success("Bucket updated: {$updatedBucket->name}");
    }

    protected function updateBucket(ObjectStorageBucket $bucket): ObjectStorageBucket
    {
        spin(
            fn () => $this->client->objectStorageBuckets()->update(
                new UpdateObjectStorageBucketRequestData(
                    bucketId: $bucket->id,
                    name: $this->form()->get('name'),
                    visibility: $this->form()->get('visibility'),
                ),
            ),
            'Updating bucket...',
        );

        return $this->client->objectStorageBuckets()->get($bucket->id);
    }

    protected function defineFields(ObjectStorageBucket $bucket): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    required: true,
                    default: $value ?? $bucket->name,
                ),
            ),
        )->setPreviousValue($bucket->name);

        $this->form()->define(
            'visibility',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Visibility',
                    options: [
                        'private' => 'Private',
                        'public' => 'Public',
                    ],
                    default: $value ?? $bucket->visibility->value,
                    required: true,
                ),
            ),
        )->setPreviousValue($bucket->visibility->value);
    }

    protected function collectDataAndUpdate(ObjectStorageBucket $bucket): ObjectStorageBucket
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateBucket($bucket);
    }
}
