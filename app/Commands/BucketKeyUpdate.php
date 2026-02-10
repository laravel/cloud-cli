<?php

namespace App\Commands;

use App\Client\Requests\UpdateBucketKeyRequestData;
use App\Dto\BucketKey;
use App\Dto\ObjectStorageBucket;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BucketKeyUpdate extends BaseCommand
{
    protected $signature = 'bucket-key:update
                            {bucket? : The bucket ID or name}
                            {key? : The key ID or name}
                            {--name= : Key name}
                            {--permission= : Permission (read_only or read_write)}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a bucket key';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Bucket Key');

        $bucket = $this->resolvers()->objectStorageBucket()->from($this->argument('bucket'));
        $key = $this->resolvers()->bucketKey()->from($bucket, $this->argument('key'));

        $this->defineFields($key);

        foreach ($this->form()->filled() as $fieldKey => $resolver) {
            $this->reportChange(
                $resolver->label(),
                $resolver->previousValue(),
                $resolver->value(),
            );
        }

        $updatedKey = $this->resolveUpdatedKey($bucket, $key);

        $this->outputJsonIfWanted($updatedKey);

        success('Bucket key updated');

        outro("Bucket key updated: {$updatedKey->name}");
    }

    protected function resolveUpdatedKey(ObjectStorageBucket $bucket, BucketKey $key): BucketKey
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('No fields to update. Provide --name or --permission.');

                throw new CommandExitException(self::FAILURE);
            }

            return $this->updateKey($bucket, $key);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($bucket, $key),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        return $this->updateKey($bucket, $key);
    }

    protected function updateKey(ObjectStorageBucket $bucket, BucketKey $key): BucketKey
    {
        spin(
            fn () => $this->client->bucketKeys()->update(new UpdateBucketKeyRequestData(
                bucketId: $bucket->id,
                keyId: $key->id,
                name: $this->form()->get('name'),
                permission: $this->form()->get('permission'),
            )),
            'Updating key...',
        );

        return $this->client->bucketKeys()->get($bucket->id, $key->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the bucket key?');
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

        $this->form()->define(
            'permission',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Permission',
                    options: [
                        'read_only' => 'Read only',
                        'read_write' => 'Read write',
                    ],
                    default: $value ?? $key->permission,
                    required: true,
                ),
            ),
        )->setPreviousValue($key->permission);
    }

    protected function collectDataAndUpdate(ObjectStorageBucket $bucket, BucketKey $key): BucketKey
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $k) => [
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

        return $this->updateKey($bucket, $key);
    }
}
