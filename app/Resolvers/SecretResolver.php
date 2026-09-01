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

    public function from(?string $id = null): ?Secret
    {
        // An unknown ID is not passed on to the picker: silently prompting would hide the typo.
        $secret = $id ? $this->fromIdentifier($id) : $this->fromInput();

        if (! $secret) {
            $this->failAndExit('Unable to resolve secret: '.($id ?? 'Provide a valid secret ID.').'. Run `cloud secret:list --json` to see available secret IDs.');
        }

        $this->displayResolved('Secret', $secret->key, $secret->id);

        return $secret;
    }

    // The API has no endpoint for a single secret, so the ID is matched against the list.
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

        $this->ensureInteractive('Multiple secrets found. Provide a secret ID.', ['options' => $options]);

        $selected = select(
            label: 'Secret',
            options: $options,
            info: fn ($id) => $id,
        );

        $this->displayResolved = false;

        return $secrets->firstWhere('id', $selected);
    }

    // Only IDs are accepted: secret names are not unique within an organization.
    public function fromCollection(Collection|LazyCollection $secrets, string $id): ?Secret
    {
        return $secrets->firstWhere('id', $id);
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
