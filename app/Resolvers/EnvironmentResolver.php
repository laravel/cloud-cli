<?php

namespace App\Resolvers;

use App\Client\Resources\Concerns\HasIncludes;
use App\Dto\Environment;
use App\Git;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\select;
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
        $identifier = $idOrName ?? $this->localConfig->environmentId();
        $environment = $identifier
            ? $this->fromIdentifier($identifier)
            : $this->fromBranch() ?? $this->fromInput();

        if (! $environment) {
            $this->failAndExit(match (true) {
                $idOrName !== null => "Unable to resolve environment: {$idOrName}.",
                $identifier !== null => "Unable to resolve environment {$identifier} from {$this->localConfig->path()}.",
                default => 'Unable to resolve environment. Provide a valid environment ID or name.',
            }.' Run `cloud environment:list --json` to see available environments.');
        }

        if (! $this->fetched) {
            // Fetch the entire environment in case we need includes and such
            $environment = $this->fetch($environment->id);
        }

        $this->displayResolved('Environment', $environment->name, $environment->id);

        return $environment;
    }

    public function fromBranch(): ?Environment
    {
        // The branch name only comes back when it's included.
        $includes = array_values(array_unique([...($this->includes ?? []), 'branch']));

        $envs = $this->client->environments()->include(...$includes)->list($this->application()->id)->collect();
        $localBranch = app(Git::class)->currentBranch();

        $matches = $envs->where('branch', $localBranch)->values();

        if ($matches->count() <= 1) {
            return $matches->first();
        }

        $options = $matches->mapWithKeys(fn (Environment $env) => [$env->id => $env->name])->toArray();

        if (! $this->ensureInteractive("More than one environment deploys the {$localBranch} branch. Provide an environment ID or name.", ['options' => $options])) {
            return null;
        }

        $selectedEnv = select(
            label: 'Environment',
            options: $options,
            info: fn ($id) => $id,
            hint: "More than one environment deploys the {$localBranch} branch.",
        );

        // No need to display the resolved environment name, it will be displayed from the select above
        $this->displayResolved = false;

        return $matches->firstWhere('id', $selectedEnv);
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

        $options = $envs->mapWithKeys(fn ($env) => [$env->id => $env->name])->toArray();

        $this->ensureInteractive('Multiple environments found. Provide an environment ID or name.', ['options' => $options]);

        $selectedEnv = select(
            label: 'Environment',
            options: $options,
            default: $envs->firstWhere('id', $this->application()->defaultEnvironmentId)?->id,
            info: fn ($id) => $id,
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
