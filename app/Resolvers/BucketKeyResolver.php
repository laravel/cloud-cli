<?php

namespace App\Resolvers;

use App\Dto\BucketKey;
use App\Dto\ObjectStorageBucket;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\spin;

class BucketKeyResolver extends Resolver
{
    public function from(ObjectStorageBucket $bucket, ?string $keyIdOrName = null): ?BucketKey
    {
        $key = ($keyIdOrName ? $this->fromIdentifier($bucket, $keyIdOrName) : null)
            ?? $this->fromInput($bucket);

        if (! $key) {
            $this->failAndExit('Unable to resolve bucket key: '.($keyIdOrName ?? 'Provide a valid key ID or name.').'. Run `cloud bucket-key:list --json` to see available keys.');
        }

        $this->displayResolved('Key', $key->name, $key->id);

        return $key;
    }

    public function fromIdentifier(ObjectStorageBucket $bucket, string $identifier): ?BucketKey
    {
        $keys = $this->fetchAll($bucket);

        return $keys->firstWhere('id', $identifier)
            ?? $keys->firstWhere('name', $identifier);
    }

    public function fromInput(ObjectStorageBucket $bucket): ?BucketKey
    {
        $keys = $this->fetchAll($bucket);

        if ($keys->isEmpty()) {
            $this->failAndExit('No keys found for this bucket.');
        }

        if ($keys->hasSole()) {
            return $keys->first();
        }

        $options = $keys->mapWithKeys(fn (BucketKey $k) => [$k->id => $k->name])->toArray();

        $this->ensureInteractive('Multiple keys found. Provide a key ID or name.', ['options' => $options]);

        $selected = selectWithContext(
            label: 'Key',
            options: $options,
        );

        $this->displayResolved = false;

        return $keys->firstWhere('id', $selected);
    }

    protected function fetchAll(ObjectStorageBucket $bucket): LazyCollection
    {
        return spin(
            fn () => $this->client->bucketKeys()->list($bucket->id)->collect(),
            'Fetching keys...',
        );
    }

    protected function idPrefix(): string
    {
        return 'flsk-';
    }
}
