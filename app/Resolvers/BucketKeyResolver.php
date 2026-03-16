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
            if ($keyIdOrName === null) {
                $this->failAndExit('No bucket key could be resolved. Provide a valid key ID or name as an argument.');
            } elseif ($this->looksLikeId($keyIdOrName)) {
                $this->failAndExit("Bucket key '{$keyIdOrName}' not found. Verify the ID is correct and belongs to this bucket.");
            } else {
                $this->failAndExit("No bucket key named '{$keyIdOrName}' found for this bucket.");
            }
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

        $this->ensureInteractive('Please provide a key ID or name.');

        $selected = selectWithContext(
            label: 'Key',
            options: $keys->mapWithKeys(fn (BucketKey $k) => [$k->id => $k->name])->toArray(),
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
