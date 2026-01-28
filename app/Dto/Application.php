<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

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
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $repositoryId = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $defaultEnvironmentId = null,
        public readonly ?Organization $organization = null,
        #[DataCollectionOf(Environment::class)]
        public readonly array $environments = [],
        #[DataCollectionOf(Deployment::class)]
        public readonly array $deployments = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'name' => $attributes['name'],
            'slug' => $attributes['slug'],
            'region' => $attributes['region'],
            'slackChannel' => $attributes['slack_channel'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ];

        if (isset($attributes['repository'])) {
            $transformed['repositoryFullName'] = $attributes['repository']['full_name'] ?? null;
            $transformed['repositoryBranch'] = $attributes['repository']['default_branch'] ?? null;
        }

        if (isset($relationships['repository']['data']['id'])) {
            $transformed['repositoryId'] = $relationships['repository']['data']['id'];
        }

        if (isset($relationships['organization']['data']['id'])) {
            $transformed['organizationId'] = $relationships['organization']['data']['id'];
            $orgData = self::resolveIncluded($included, $relationships['organization'], 'organizations');

            if ($orgData) {
                $transformed['organization'] = Organization::createFromResponse(['data' => $orgData, 'included' => $included])->toArray();
            }
        }

        if (isset($relationships['environments']['data'])) {
            $transformed['environmentIds'] = array_column($relationships['environments']['data'], 'id');
            $envData = self::resolveIncludedCollection($included, $relationships['environments'], 'environments');
            $transformed['environments'] = collect($envData)->map(fn ($item) => Environment::createFromResponse(['data' => $item, 'included' => $included])->toArray())->toArray();
        }

        if (isset($relationships['deployments']['data'])) {
            $transformed['deploymentIds'] = array_column($relationships['deployments']['data'], 'id');
            $deployData = self::resolveIncludedCollection($included, $relationships['deployments'], 'deployments');
            $transformed['deployments'] = collect($deployData)->map(fn ($item) => Deployment::createFromResponse(['data' => $item, 'included' => $included])->toArray())->toArray();
        }

        if (isset($relationships['defaultEnvironment']['data']['id'])) {
            $transformed['defaultEnvironmentId'] = $relationships['defaultEnvironment']['data']['id'];
        }

        return self::from($transformed);
    }

    public function url(): string
    {
        $environment = collect($this->environments)->firstWhere('id', $this->defaultEnvironmentId);

        $parts = [
            config('app.base_url'),
            $this->organization->slug,
            $this->slug,
        ];

        if ($environment) {
            $parts[] = $environment->name;
        }

        return implode('/', $parts);
    }

    protected static function resolveIncluded(array $included, ?array $relationship, string $type): ?array
    {
        if (! $relationship || ! isset($relationship['data']['id'])) {
            return null;
        }

        $id = $relationship['data']['id'];

        return collect($included)
            ->first(fn ($item) => $item['type'] === $type && $item['id'] === $id);
    }

    protected static function resolveIncludedCollection(array $included, ?array $relationship, string $type): array
    {
        if (! $relationship || ! isset($relationship['data']) || ! is_array($relationship['data'])) {
            return [];
        }

        $ids = array_column($relationship['data'], 'id');

        return collect($included)
            ->filter(fn ($item) => $item['type'] === $type && in_array($item['id'], $ids))
            ->values()
            ->toArray();
    }
}
