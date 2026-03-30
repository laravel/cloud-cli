<?php

namespace App\Dto;

use App\Enums\EnvironmentStatus;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class Environment extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $name,
        public readonly ?string $branch,
        public readonly ?string $status,
        public readonly ?array $instances,
        public readonly ?string $buildCommand,
        public readonly ?string $deployCommand,
        public readonly string $slug,
        #[WithCast(EnumCast::class)]
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
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
        public readonly ?Application $application = null,
        public readonly ?string $branchId = null,
        public readonly array $deploymentIds = [],
        public readonly ?string $currentDeploymentId = null,
        public readonly array $domainIds = [],
        public readonly ?string $primaryDomainId = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $vanityDomain = $attributes['vanity_domain'] ?? '';

        $transformed = [
            'id' => $data['id'],
            'name' => $attributes['name'],
            'url' => $vanityDomain ? str($vanityDomain)->start('https://') : '',
            'branch' => null,
            'status' => $attributes['status'] ?? null,
            'buildCommand' => $attributes['build_command'] ?? null,
            'deployCommand' => $attributes['deploy_command'] ?? null,
            'slug' => $attributes['slug'] ?? '',
            'statusEnum' => $attributes['status'] ?? 'stopped',
            'createdFromAutomation' => $attributes['created_from_automation'] ?? false,
            'vanityDomain' => $vanityDomain,
            'phpMajorVersion' => $attributes['php_major_version'] ?? '',
            'nodeVersion' => $attributes['node_version'] ?? null,
            'usesOctane' => $attributes['uses_octane'] ?? false,
            'usesHibernation' => $attributes['uses_hibernation'] ?? false,
            'usesPushToDeploy' => $attributes['uses_push_to_deploy'] ?? false,
            'usesDeployHook' => $attributes['uses_deploy_hook'] ?? false,
            'environmentVariables' => $attributes['environment_variables'] ?? [],
            'networkSettings' => $attributes['network_settings'] ?? [],
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
        ];

        if (isset($relationships['instances']['data'])) {
            $transformed['instances'] = array_column($relationships['instances']['data'], 'id');
        }

        if (isset($relationships['application']['data'])) {
            $app = collect($included)->firstWhere('type', 'applications');
            $transformed['application'] = Application::createFromResponse(['data' => $app, 'included' => $included]);
        }

        if (isset($relationships['branch']['data']['id'])) {
            $branch = collect($included)->first(fn ($item) => $item['type'] === 'branches' && $item['id'] === $relationships['branch']['data']['id']);
            $transformed['branch'] = $branch['attributes']['name'] ?? null;
        }

        if (isset($relationships['deployments']['data'])) {
            $transformed['deploymentIds'] = array_column($relationships['deployments']['data'], 'id');
        }

        if (isset($relationships['currentDeployment']['data']['id'])) {
            $transformed['currentDeploymentId'] = $relationships['currentDeployment']['data']['id'];
        }

        if (isset($relationships['domains']['data'])) {
            $transformed['domainIds'] = array_column($relationships['domains']['data'], 'id');
        }

        if (isset($relationships['primaryDomain']['data']['id'])) {
            $transformed['primaryDomainId'] = $relationships['primaryDomain']['data']['id'];
        }

        return self::from($transformed);
    }
}
