<?php

namespace App\Dto;

use App\Enums\EnvironmentStatus;
use Carbon\CarbonImmutable;

class Environment
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $name,
        public readonly ?string $branch,
        public readonly ?string $status,
        public readonly array $instances,
        public readonly ?string $buildCommand,
        public readonly ?string $deployCommand,
        public readonly string $slug,
        public readonly EnvironmentStatus $statusEnum,
        public readonly bool $createdFromAutomation,
        public readonly string $vanityDomain,
        public readonly string $phpMajorVersion,
        public readonly ?string $nodeVersion = null,
        public readonly bool $usesOctane = false,
        public readonly bool $usesHibernation = false,
        public readonly bool $usesPushToDeploy = false,
        public readonly bool $usesDeployHook = false,
        public readonly array $environmentVariables = [],
        public readonly array $networkSettings = [],
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $applicationId = null,
        public readonly ?string $branchId = null,
        public readonly array $deploymentIds = [],
        public readonly ?string $currentDeploymentId = null,
        public readonly array $domainIds = [],
        public readonly ?string $primaryDomainId = null,
    ) {
        //
    }

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $vanityDomain = $attributes['vanity_domain'] ?? '';
        $buildCommand = $attributes['build_command'] ?? null;
        $deployCommand = $attributes['deploy_command'] ?? null;

        return new self(
            id: $data['id'],
            name: $attributes['name'],
            url: $vanityDomain ? str($vanityDomain)->start('https://') : '',
            branch: $attributes['branch'] ?? null,
            status: $attributes['status'] ?? null,
            instances: array_column($relationships['instances']['data'] ?? [], 'id'),
            buildCommand: $buildCommand,
            deployCommand: $deployCommand,
            slug: $attributes['slug'] ?? '',
            statusEnum: isset($attributes['status']) ? EnvironmentStatus::from($attributes['status']) : EnvironmentStatus::STOPPED,
            createdFromAutomation: $attributes['created_from_automation'] ?? false,
            vanityDomain: $vanityDomain,
            phpMajorVersion: $attributes['php_major_version'] ?? '',
            nodeVersion: $attributes['node_version'] ?? null,
            usesOctane: $attributes['uses_octane'] ?? false,
            usesHibernation: $attributes['uses_hibernation'] ?? false,
            usesPushToDeploy: $attributes['uses_push_to_deploy'] ?? false,
            usesDeployHook: $attributes['uses_deploy_hook'] ?? false,
            environmentVariables: $attributes['environment_variables'] ?? [],
            networkSettings: $attributes['network_settings'] ?? [],
            createdAt: isset($attributes['created_at']) ? CarbonImmutable::parse($attributes['created_at']) : null,
            applicationId: $relationships['application']['data']['id'] ?? null,
            branchId: $relationships['branch']['data']['id'] ?? null,
            deploymentIds: array_column($relationships['deployments']['data'] ?? [], 'id'),
            currentDeploymentId: $relationships['currentDeployment']['data']['id'] ?? null,
            domainIds: array_column($relationships['domains']['data'] ?? [], 'id'),
            primaryDomainId: $relationships['primaryDomain']['data']['id'] ?? null,
        );
    }
}
