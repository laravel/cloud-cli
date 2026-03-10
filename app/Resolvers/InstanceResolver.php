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
            $this->failAndExit('Unable to resolve instance: '.($idOrName ?? 'Provide a valid instance ID or name as an argument.'));
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
            answered(label: 'Instance', answer: $instances->first());

            return $instances->first();
        }

        $this->ensureInteractive('Please provide an instance ID or name.');

        $selected = selectWithContext(
            label: 'Instance',
            options: $instances->mapWithKeys(fn ($instance) => [
                $instance->id => $instance->name,
            ])->toArray(),
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
