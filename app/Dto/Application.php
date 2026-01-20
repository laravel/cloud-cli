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
        public readonly ?string $repositoryProvider = null,
        public readonly ?string $repositoryBranch = null,
        public readonly ?string $slackChannel = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?string $repositoryId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $defaultEnvironmentId = null,
    ) {
        //
    }

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        $repository = $attributes['repository'] ?? [];
        $relationships = $data['relationships'] ?? [];

        return new self(
            id: $data['id'],
            name: $attributes['name'] ?? $data['name'],
            slug: $attributes['slug'] ?? $data['slug'] ?? '',
            region: $attributes['region'] ?? $data['region'] ?? '',
            repositoryFullName: $repository['full_name'] ?? null,
            repositoryProvider: $repository['provider'] ?? null,
            repositoryBranch: $repository['default_branch'] ?? null,
            slackChannel: $attributes['slack_channel'] ?? null,
            createdAt: $attributes['created_at'] ? CarbonImmutable::parse($attributes['created_at']) : null,
            updatedAt: isset($attributes['updated_at']) ? CarbonImmutable::parse($attributes['updated_at']) : null,
            repositoryId: $relationships['repository']['data']['id'] ?? null,
            organizationId: $relationships['organization']['data']['id'] ?? null,
            environmentIds: array_column($relationships['environments']['data'] ?? [], 'id'),
            deploymentIds: array_column($relationships['deployments']['data'] ?? [], 'id'),
            defaultEnvironmentId: $relationships['defaultEnvironment']['data']['id'] ?? null,
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
                'provider' => $this->repositoryProvider,
                'branch' => $this->repositoryBranch,
            ],
            'slack_channel' => $this->slackChannel,
            'environment_ids' => $this->environmentIds,
            'default_environment_id' => $this->defaultEnvironmentId,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
