<?php

namespace App\Resolvers;

use App\Dto\Secret;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class SecretResolver extends Resolver
{
    public function resolve(): ?Secret
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?Secret
    {
        $secret = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $secret) {
            $this->failAndExit('Unable to resolve secret: '.($idOrName ?? 'Provide a valid secret ID or name.').'. Run `cloud secret:list --json` to see available secrets.');
        }

        $this->displayResolved('Secret', $secret->key, $secret->id);

        return $secret;
    }

    // The API has no endpoint for a single secret, so an ID is matched against the list too.
    public function fromIdentifier(string $identifier): ?Secret
    {
        return $this->fromCollection($this->fetchAll(), $identifier);
    }

    public function fromInput(): ?Secret
    {
        $secrets = $this->fetchAll();

        if ($secrets->isEmpty()) {
            $this->failAndExit('No secrets found.');
        }

        if ($secrets->hasSole()) {
            return $secrets->first();
        }

        $options = $secrets->mapWithKeys(fn (Secret $secret) => [$secret->id => $secret->key])->toArray();

        $this->ensureInteractive('Multiple secrets found. Provide a secret ID or name.', ['options' => $options]);

        $selected = select(
            label: 'Secret',
            options: $options,
            info: fn ($id) => $id,
        );

        $this->displayResolved = false;

        return $secrets->firstWhere('id', $selected);
    }

    public function fromCollection(Collection|LazyCollection $secrets, string $identifier): ?Secret
    {
        return $secrets->firstWhere('id', $identifier)
            ?? $secrets->firstWhere('key', $identifier);
    }

    protected function fetchAll(): LazyCollection
    {
        return spin(
            fn () => $this->client->secrets()->list()->collect(),
            'Fetching secrets...',
        );
    }

    protected function idPrefix(): string
    {
        return 'scrt-';
    }
}
