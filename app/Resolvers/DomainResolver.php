<?php

namespace App\Resolvers;

use App\Dto\Domain;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\spin;

class DomainResolver extends Resolver
{
    use HasAnApplication;

    public function from(?string $idOrName = null): ?Domain
    {
        $domain = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $domain) {
            $this->failAndExit('Unable to resolve domain: '.($idOrName ?? 'Provide a valid domain ID or name.').'. Run `cloud domain:list --json` to see available domains.');
        }

        $this->displayResolved('Domain', $domain->name, $domain->id);

        return $domain;
    }

    public function fromIdentifier(string $identifier): ?Domain
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->domains()->get($identifier),
                'Fetching domain...',
            ),
            fn () => $this->resolveFromName($identifier),
        );
    }

    protected function resolveFromName(string $name): ?Domain
    {
        $environment = $this->resolvers()
            ->environment()
            ->withApplication($this->application())
            ->resolve();

        $domains = $this->client->domains()->list($environment->id)->collect();

        return $domains->firstWhere('name', $name);
    }

    public function fromInput(): ?Domain
    {
        $environment = $this->resolvers()
            ->environment()
            ->withApplication($this->application())
            ->resolve();

        $domains = $this->client->domains()->list($environment->id)->collect();

        if ($domains->isEmpty()) {
            $this->failAndExit('No domains found for environment '.$environment->name);
        }

        if ($domains->hasSole()) {
            answered(label: 'Domain', answer: $domains->first()->name);

            return $domains->first();
        }

        $options = $domains->mapWithKeys(fn ($domain) => [
            $domain->id => $domain->name,
        ])->toArray();

        $this->ensureInteractive('Multiple domains found. Provide a domain ID or name.', ['options' => $options]);

        $selected = selectWithContext(
            label: 'Domain',
            options: $options,
        );

        $this->displayResolved = false;

        return $domains->firstWhere('id', $selected);
    }

    protected function idPrefix(): string
    {
        return 'domain-';
    }
}
