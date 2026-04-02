<?php

namespace App\Resolvers;

use App\Dto\Deployment;
use App\Resolvers\Concerns\HasAnEnvironment;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;

class DeploymentResolver extends Resolver
{
    use HasAnEnvironment;

    public function resolve(): ?Deployment
    {
        return $this->from();
    }

    public function from(?string $id = null): ?Deployment
    {
        $deployment = ($id ? $this->fromIdentifier($id) : null) ?? $this->fromInput();

        if (! $deployment) {
            if ($id) {
                $this->failAndExit('Unable to resolve deployment: '.$id.'. Run `cloud deployment:list --json` to see available deployments.');
            }

            return null;
        }

        $this->displayResolved('Deployment', $deployment->id, $deployment->startedAt?->toIso8601String());

        return $deployment;
    }

    public function fromIdentifier(string $identifier): ?Deployment
    {
        /** @var Deployment|null */
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->deployments()->include('environment')->get($identifier),
                'Fetching deployment...',
            ),
        );
    }

    public function fromInput(): ?Deployment
    {
        $environment = $this->environment();
        /** @var Collection<int, Deployment> $deployments */
        $deployments = collect(
            spin(
                fn () => $this->client->deployments()->include('environment')->list($environment->id)->items(),
                'Fetching deployments...',
            ),
        );

        if ($deployments->isEmpty()) {
            return null;
        }

        if ($deployments->hasSole()) {
            return $deployments->first();
        }

        $options = $deployments->mapWithKeys(fn (Deployment $deployment) => [
            $deployment->id => $deployment->startedAt?->toIso8601String().' ('.str($deployment->commitMessage)->limit(10).')',
        ])->toArray();

        $this->ensureInteractive('Multiple deployments found. Provide a deployment ID.', ['options' => $options]);

        $selection = selectWithContext(
            label: 'Deployment',
            options: $options,
        );

        $this->displayResolved = false;

        /** @var Deployment|null */
        return $deployments->firstWhere('id', $selection);
    }

    protected function idPrefix(): string
    {
        return 'depl-';
    }
}
