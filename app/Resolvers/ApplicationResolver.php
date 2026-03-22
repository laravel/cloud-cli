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
        $identifier = $idOrName ?? $this->applicationFlag ?? $this->localConfig->applicationId();

        $app = ($identifier ? $this->fromIdentifier($identifier) : null)
            ?? $this->fromRepo()
            ?? $this->fromInput();

        if (! $app) {
            if ($identifier && $this->looksLikeId($identifier)) {
                $this->failAndExit("Application '{$identifier}' not found. Verify the ID is correct and belongs to your organization.");
            } elseif ($identifier) {
                $this->failAndExit("No application named '{$identifier}' found in your organization.");
            } else {
                $repository = app(Git::class)->remoteRepo();

                if ($repository) {
                    $this->failAndExit("No application found matching repository '{$repository}'. Create one or provide an application ID or name as an argument.");
                } else {
                    $this->failAndExit('No application could be resolved. Provide a valid application ID or name as an argument.');
                }
            }
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

        $this->ensureInteractive('Please provide an application ID or name.');

        $selectedApp = selectWithContext(
            label: 'Application',
            options: $repoApps->mapWithKeys(fn ($app) => [$app->id => $app->name])->toArray(),
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

        $this->ensureInteractive('Please provide an application ID or name.');

        $selectedApp = selectWithContext(
            label: 'Application',
            options: $apps->mapWithKeys(fn ($app) => [$app->id => $app->name])->toArray(),
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
