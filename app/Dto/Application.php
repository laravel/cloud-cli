<?php

namespace App\Dto;

use Carbon\CarbonImmutable;

class Application extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $region,
        public readonly array $environmentIds = [],
        public readonly array $deploymentIds = [],
        public readonly ?string $repositoryFullName = null,
        public readonly ?string $repositoryBranch = null,
        public readonly ?string $slackChannel = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $repositoryId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $defaultEnvironmentId = null,
        public readonly ?Organization $organization = null,
        public readonly array $environments = [],
        public readonly array $deployments = [],
    ) {
        //
    }

    public static function fromApiResponse(array $response, ?array $item = null): self
    {
        $data = $item ?? $response['data'];
        $included = $response['included'] ?? [];

        $attributes = $data['attributes'];
        $repository = $attributes['repository'] ?? null;
        $relationships = $data['relationships'] ?? [];

        $organizationId = $relationships['organization']['data']['id'] ?? null;
        $environmentIds = array_column($relationships['environments']['data'] ?? [], 'id');
        $deploymentIds = array_column($relationships['deployments']['data'] ?? [], 'id');

        $organization = null;

        if ($organizationId) {
            $orgData = collect($included)->first(fn ($item) => $item['type'] === 'organizations' && $item['id'] === $organizationId);
            if ($orgData) {
                $organization = Organization::fromApiResponse(['data' => $orgData], $orgData);
            }
        }

        $environments = collect($included)
            ->filter(fn ($item) => $item['type'] === 'environments' && in_array($item['id'], $environmentIds))
            ->map(fn ($item) => Environment::fromApiResponse(['data' => $item], $item))
            ->values()
            ->toArray();

        $deployments = collect($included)
            ->filter(fn ($item) => $item['type'] === 'deployments' && in_array($item['id'], $deploymentIds))
            ->map(fn ($item) => Deployment::fromApiResponse(['data' => $item], $item))
            ->values()
            ->toArray();

        return new self(
            id: $data['id'],
            name: $attributes['name'],
            slug: $attributes['slug'],
            region: $attributes['region'],
            repositoryFullName: $repository ? ($repository['full_name'] ?? null) : null,
            repositoryBranch: $repository ? ($repository['default_branch'] ?? null) : null,
            slackChannel: $attributes['slack_channel'] ?? null,
            createdAt: $attributes['created_at'] ? CarbonImmutable::parse($attributes['created_at']) : null,
            repositoryId: $relationships['repository']['data']['id'] ?? null,
            organizationId: $organizationId,
            environmentIds: $environmentIds,
            deploymentIds: $deploymentIds,
            defaultEnvironmentId: $relationships['defaultEnvironment']['data']['id'] ?? null,
            organization: $organization,
            environments: $environments,
            deployments: $deployments,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'region' => $this->region,
            'repository' => [
                'full_name' => $this->repositoryFullName,
                'default_branch' => $this->repositoryBranch,
            ],
            'slack_channel' => $this->slackChannel,
            'environment_ids' => $this->environmentIds,
            'default_environment_id' => $this->defaultEnvironmentId,
            'created_at' => $this->createdAt?->toIso8601String(),
        ];
    }
}
