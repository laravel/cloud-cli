<?php

namespace App\Concerns;

use App\Enums\SourceProvider;
use App\SourceProviders\SourceProviderManager;

use function Laravel\Prompts\select;

trait ResolvesSourceProvider
{
    /**
     * The provider for a repository we are creating an application from. The origin
     * remote answers this outright unless its host is one we do not recognise.
     */
    protected function resolveSourceProvider(): SourceProvider
    {
        $this->defineSourceProviderField();

        if ($given = $this->form()->get('source_provider')) {
            return $this->sourceProviderFrom($given);
        }

        if ($detected = app(SourceProviderManager::class)->detect()) {
            return $detected;
        }

        $this->form()->prompt('source_provider');

        return $this->sourceProviderFrom($this->form()->get('source_provider'));
    }

    protected function defineSourceProviderField(): void
    {
        $this->form()->define(
            'source_provider',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Source provider',
                    options: SourceProvider::options(),
                    default: $value ?? SourceProvider::GITHUB->value,
                ))
                ->nonInteractively(fn () => SourceProvider::GITHUB->value),
        );
    }

    protected function sourceProviderFrom(?string $value): ?SourceProvider
    {
        if ($value === null) {
            return null;
        }

        $provider = SourceProvider::tryFrom($value);

        if ($provider === null) {
            $this->failAndExit(
                'Unknown source provider ['.$value.']. Use one of: '
                .implode(', ', array_keys(SourceProvider::options())),
            );
        }

        return $provider;
    }
}
