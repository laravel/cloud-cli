<?php

namespace App\Resolvers;

use App\Client\Resources\Concerns\HasIncludes;
use App\Dto\Environment;
use App\Git;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\spin;

class EnvironmentResolver extends Resolver
{
    use HasAnApplication;
    use HasIncludes;

    protected bool $fetched = false;

    public function resolve(): ?Environment
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?Environment
    {
        $this->include('application', 'branch', 'deployments', 'currentDeployment', 'primaryDomain', 'instances');

        $identifier = $idOrName ?? $this->localConfig->environmentId();
        $environment = ($identifier ? $this->fromIdentifier($identifier) : null) ?? $this->fromBranch() ?? $this->fromInput();

        if (! $environment) {
            $this->failAndExit('Unable to resolve environment: '.($idOrName ?? 'Provide a valid environment ID or name as an argument.'));
        }

        if (! $this->fetched) {
            // Fetch the entire environment in case we need includes and such
            $environment = $this->fetch($environment->id);
        }

        $this->displayResolved('Environment', $environment->name, $environment->id);

        return $environment;
    }

    public function fromBranch()
    {
        $envs = $this->client->environments()->include(...($this->includes ?? []))->list($this->application()->id)->collect();
        $localbranch = app(Git::class)->currentBranch();

        return $envs->firstWhere('branch', $localbranch);
    }

    public function fromIdentifier(string $identifier): ?Environment
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->fetch($identifier),
                'Fetching environment...',
            ),
            fn () => $this->resolveFromApplication($identifier),
        );
    }

    public function resolveFromApplication(string $identifier): ?Environment
    {
        $envs = collect($this->application()->environments);

        return $envs->firstWhere('id', $identifier) ?? $envs->firstWhere('name', $identifier);
    }

    public function fromInput(): ?Environment
    {
        $envs = collect($this->application()->environments);

        if ($envs->hasSole()) {
            return $envs->first();
        }

        $this->ensureInteractive('Please provide an environment ID or name.');

        $selectedEnv = selectWithContext(
            label: 'Environment',
            options: $envs->mapWithKeys(fn ($env) => [$env->id => $env->name])->toArray(),
            default: $envs->firstWhere('id', $this->application()->defaultEnvironmentId)?->id,
        );

        // No need to display the resolved environment name, it will be displayed from the select above
        $this->displayResolved = false;

        return $envs->firstWhere('id', $selectedEnv);
    }

    protected function fetch(string $identifier): Environment
    {
        $this->fetched = true;

        return $this->client->environments()->include(...($this->includes ?? []))->get($identifier);
    }

    protected function idPrefix(): string
    {
        return 'env-';
    }
}
