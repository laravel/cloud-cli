<?php

namespace App\Resolvers;

use App\Dto\Application;
use App\Git;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class ApplicationResolver extends Resolver
{
    public function resolve(): ?Application
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?Application
    {
        $identifier = $idOrName ?? $this->localConfig->applicationId();

        $app = ($identifier ? $this->fromIdentifier($identifier) : null)
            ?? $this->fromRepo()
            ?? $this->fromInput();

        if (! $app) {
            if ($this->nullable) {
                return null;
            }

            $this->failAndExit('Unable to resolve application: '.($idOrName ?? 'Provide a valid application ID or name.').'. Run `cloud application:list --json` to see available applications.');
        }

        $this->displayResolved('Application', $app->name, $app->id);

        return $app;
    }

    public function fromIdentifier(string $identifier): ?Application
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->applications()->withDefaultIncludes()->get($identifier),
                'Fetching application...',
            ),
            fn () => $this->fetchAndFind($identifier),
        );
    }

    public function fromRepo(): ?Application
    {
        $repository = app(Git::class)->remoteRepo();

        if (! $repository) {
            return null;
        }

        $apps = $this->fetchAll();

        $repoApps = $apps->where('repositoryFullName', $repository);

        if ($repoApps->isEmpty()) {
            return null;
        }

        if ($repoApps->hasSole()) {
            return $repoApps->first();
        }

        $options = $repoApps->mapWithKeys(fn ($app) => [$app->id => $app->name])->toArray();

        if (! $this->ensureInteractive('Multiple applications found. Provide an application ID or name.', ['options' => $options])) {
            return null;
        }

        $selectedApp = selectWithContext(
            label: 'Application',
            options: $options,
        );

        // No need to display the resolved application name, it will be displayed from the select above
        $this->displayResolved = false;

        return $repoApps->firstWhere('id', $selectedApp);
    }

    public function fromInput(): ?Application
    {
        $apps = $this->fetchAll();

        if ($apps->hasSole()) {
            return $apps->first();
        }

        $options = $apps->mapWithKeys(fn ($app) => [$app->id => $app->name])->toArray();

        if (! $this->ensureInteractive('Multiple applications found. Provide an application ID or name.', ['options' => $options])) {
            return null;
        }

        $selectedApp = selectWithContext(
            label: 'Application',
            options: $options,
        );

        // No need to display the resolved application name, it will be displayed from the select above
        $this->displayResolved = false;

        return $this->fromCollection($apps, $selectedApp);
    }

    public function fromCollection(Collection|LazyCollection $apps, string $identifier): ?Application
    {
        return $apps->firstWhere('id', $identifier) ?? $apps->firstWhere('name', $identifier);
    }

    public function fetchAndFind(string $identifier): ?Application
    {
        return $this->fromCollection($this->fetchAll(), $identifier);
    }

    protected function fetchAll(): Collection|LazyCollection
    {
        return collect(spin(
            fn () => $this->client->applications()->withDefaultIncludes()->list()->items(),
            'Fetching applications...',
        ));
    }

    protected function idPrefix(): string
    {
        return 'app-';
    }
}
