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
            if ($idOrName === null) {
                $this->failAndExit('No domain could be resolved. Provide a valid domain ID or name as an argument.');
            } elseif ($this->looksLikeId($idOrName)) {
                $this->failAndExit("Domain '{$idOrName}' not found. Verify the ID is correct and belongs to your environment.");
            } else {
                $this->failAndExit("No domain named '{$idOrName}' found for this environment.");
            }
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

        $this->ensureInteractive('Please provide a domain ID or name.');

        $selected = selectWithContext(
            label: 'Domain',
            options: $domains->mapWithKeys(fn ($domain) => [
                $domain->id => $domain->name,
            ])->toArray(),
        );

        $this->displayResolved = false;

        return $domains->firstWhere('id', $selected);
    }

    protected function idPrefix(): string
    {
        return 'domain-';
    }
}
