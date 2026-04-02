<?php

namespace App\Resolvers;

use App\Dto\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\spin;

class CacheResolver extends Resolver
{
    public function resolve(): ?Cache
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?Cache
    {
        $cache = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $cache) {
            $this->failAndExit('Unable to resolve cache: '.($idOrName ?? 'Provide a valid cache ID or name.').'. Run `cloud cache:list --json` to see available caches.');
        }

        $this->displayResolved('Cache', $cache->name, $cache->id);

        return $cache;
    }

    public function fromIdentifier(string $identifier): ?Cache
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->caches()->get($identifier),
                'Fetching cache...',
            ),
            fn () => $this->fetchAndFind($identifier),
        );
    }

    public function fromInput(): ?Cache
    {
        $caches = $this->fetchAll();

        if ($caches->isEmpty()) {
            $this->failAndExit('No caches found.');
        }

        if ($caches->hasSole()) {
            return $caches->first();
        }

        $options = $caches->mapWithKeys(fn (Cache $cache) => [$cache->id => $cache->name])->toArray();

        $this->ensureInteractive('Multiple caches found. Provide a cache ID or name.', ['options' => $options]);

        $selected = selectWithContext(
            label: 'Cache',
            options: $options,
        );

        $this->displayResolved = false;

        return $caches->firstWhere('id', $selected);
    }

    public function fromCollection(Collection|LazyCollection $caches, string $identifier): ?Cache
    {
        return $caches->firstWhere('id', $identifier)
            ?? $caches->firstWhere('name', $identifier);
    }

    public function fetchAndFind(string $identifier): ?Cache
    {
        return $this->fromCollection($this->fetchAll(), $identifier);
    }

    protected function fetchAll(): LazyCollection
    {
        return spin(
            fn () => $this->client->caches()->list()->collect(),
            'Fetching caches...',
        );
    }

    protected function idPrefix(): string
    {
        return 'cache-';
    }
}
