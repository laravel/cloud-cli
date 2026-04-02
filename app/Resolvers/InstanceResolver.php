<?php

namespace App\Resolvers;

use App\Dto\EnvironmentInstance;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class InstanceResolver extends Resolver
{
    use HasAnApplication;

    public function resolve(): ?EnvironmentInstance
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?EnvironmentInstance
    {
        $instance = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $instance) {
            $this->failAndExit('Unable to resolve instance: '.($idOrName ?? 'Provide a valid instance ID or name.').'. Run `cloud instance:list --json` to see available instances.');
        }

        $this->displayResolved('Instance', $instance->name, $instance->id);

        return $instance;
    }

    public function fromIdentifier(string $identifier): ?EnvironmentInstance
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->instances()->include('environment')->get($identifier),
                'Fetching instance...',
            ),
        );
    }

    public function fromInput(): ?EnvironmentInstance
    {
        $environment = $this->resolvers()
            ->environment()
            ->withApplication($this->application())
            ->include('instances')
            ->resolve();
        $instances = $this->client->instances()->include('environment')->list($environment->id)->collect();

        if ($instances->isEmpty()) {
            $this->failAndExit('No instances found for environment '.$environment->name);
        }

        if ($instances->hasSole()) {
            answered(label: 'Instance', answer: $instances->first()->name);

            return $instances->first();
        }

        $options = $instances->mapWithKeys(fn ($instance) => [
            $instance->id => $instance->name,
        ])->toArray();

        $this->ensureInteractive('Multiple instances found. Provide an instance ID or name.', ['options' => $options]);

        $selected = selectWithContext(
            label: 'Instance',
            options: $options,
        );

        // No need to display the resolved instance name, it will be displayed from the select above
        $this->displayResolved = false;

        return $instances->firstWhere('id', $selected);
    }

    protected function idPrefix(): string
    {
        return 'inst-';
    }
}
