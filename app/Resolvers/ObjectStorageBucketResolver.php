<?php

namespace App\Resolvers;

use App\Dto\ObjectStorageBucket;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\spin;

class ObjectStorageBucketResolver extends Resolver
{
    public function resolve(): ?ObjectStorageBucket
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?ObjectStorageBucket
    {
        $bucket = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $bucket) {
            $this->failAndExit('Unable to resolve bucket: '.($idOrName ?? 'Provide a valid bucket ID or name.').'. Run `cloud bucket:list --json` to see available buckets.');
        }

        $this->displayResolved('Bucket', $bucket->name, $bucket->id);

        return $bucket;
    }

    public function fromIdentifier(string $identifier): ?ObjectStorageBucket
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->objectStorageBuckets()->get($identifier),
                'Fetching bucket...',
            ),
            fn () => $this->fetchAndFind($identifier),
        );
    }

    public function fromInput(): ?ObjectStorageBucket
    {
        $buckets = $this->fetchAll();

        if ($buckets->isEmpty()) {
            $this->failAndExit('No buckets found.');
        }

        if ($buckets->hasSole()) {
            return $buckets->first();
        }

        $options = $buckets->mapWithKeys(fn (ObjectStorageBucket $b) => [$b->id => $b->name])->toArray();

        $this->ensureInteractive('Multiple buckets found. Provide a bucket ID or name.', ['options' => $options]);

        $selected = selectWithContext(
            label: 'Bucket',
            options: $options,
        );

        $this->displayResolved = false;

        return $buckets->firstWhere('id', $selected);
    }

    public function fromCollection(Collection|LazyCollection $buckets, string $identifier): ?ObjectStorageBucket
    {
        return $buckets->firstWhere('id', $identifier)
            ?? $buckets->firstWhere('name', $identifier);
    }

    public function fetchAndFind(string $identifier): ?ObjectStorageBucket
    {
        return $this->fromCollection($this->fetchAll(), $identifier);
    }

    protected function fetchAll(): LazyCollection
    {
        return spin(
            fn () => $this->client->objectStorageBuckets()->list()->collect(),
            'Fetching buckets...',
        );
    }

    protected function idPrefix(): string
    {
        return 'fls-';
    }
}
