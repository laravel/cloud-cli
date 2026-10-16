<?php

namespace App\Commands;

use App\Client\Requests\CreateCacheRequestData;
use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\ValidatesCacheInput;
use App\Dto\Cache;
use App\Dto\CacheType;
use App\Dto\Region;
use App\Enums\CacheEvictionPolicy;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CacheCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Cache::class;

    use DeterminesDefaultRegion;
    use ValidatesCacheInput;

    protected $signature = 'cache:create
                            {--name= : Cache name}
                            {--type= : Cache type}
                            {--region= : Cache region}
                            {--size= : Cache size}
                            {--auto-upgrade-enabled= : Auto upgrade enabled}
                            {--is-public= : Is public}
                            {--eviction-policy= : Eviction policy}
                            {--uses-hibernation= : Hibernate the cache when idle}
                            {--hibernation-timeout= : Minutes idle before hibernating (1-60), required with --uses-hibernation}
                            {--cluster-id= : Dedicated cluster ID}';

    protected $description = 'Create a new cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Cache');

        $cache = $this->loopUntilValid($this->createCache(...));

        $this->outputJsonIfWanted($cache);

        success("Cache created: {$cache->name}");
    }

    protected function createCache()
    {
        $types = spin(
            fn () => $this->client->caches()->types(),
            'Fetching cache types...',
        );

        $typeOptions = collect($types)->mapWithKeys(fn (CacheType $type) => [
            $type->type => $type->label,
        ])->filter()->toArray();

        $this->form()->prompt(
            'type',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Cache type',
                    options: $typeOptions,
                    default: $value,
                    required: true,
                ))
                ->nonInteractively(fn () => null),
        );

        $type = collect($types)->first(fn (CacheType $t) => $t->type === $this->form()->get('type'));

        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Cache name',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($value) => $this->validateCacheName($value),
                ),
            ),
        );

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $this->form()->prompt(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Region',
                    options: collect($regions)
                        ->filter(fn (Region $region) => in_array($region->value, $type->regions))
                        ->mapWithKeys(fn (Region $region) => [
                            $region->value => $region->label,
                        ])->toArray(),
                    default: $value ?? $this->getDefaultRegion(),
                    required: true,
                ))
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        $this->form()->prompt(
            'size',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => select(
                label: 'Size',
                options: collect($type->sizes)->mapWithKeys(fn ($size) => [$size->value => $size->label])->toArray(),
                default: $value,
                required: true,
            )),
        );

        if ($type->supportsAutoUpgrade) {
            $this->form()->prompt(
                'auto_upgrade_enabled',
                fn ($resolver) => $resolver->fromInput(fn (?string $value) => confirm(
                    label: 'Enable auto upgrade?',
                    default: $value ?? true,
                )),
            );
        }

        $this->form()->prompt(
            'is_public',
            fn ($resolver) => $resolver->fromInput(fn (?string $value) => confirm(
                label: 'Make cache public?',
                default: $value ?? false,
            )),
        );

        if ($type->supportsEvictionPolicy) {
            $this->form()->prompt(
                'eviction_policy',
                fn ($resolver) => $resolver
                    ->fromInput(fn (?string $value) => select(
                        label: 'Eviction policy',
                        options: CacheEvictionPolicy::options(),
                        default: $value ?? CacheEvictionPolicy::ALLKEYS_LRU->value,
                    ))
                    ->nonInteractively(fn () => CacheEvictionPolicy::ALLKEYS_LRU->value),
            );
        } else {
            // Not prompted, but a passed flag still reaches the API so it can say why it was rejected.
            $this->form()->define('eviction_policy', fn ($resolver) => $resolver);
        }

        if ($type->supportsHibernationFor($this->form()->get('size'))) {
            $this->form()->prompt(
                'uses_hibernation',
                fn ($resolver) => $resolver
                    ->fromInput(fn (?string $value) => confirm(
                        label: 'Hibernate when idle?',
                        default: $value !== null ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : false,
                    ))
                    ->nonInteractively(fn () => false),
            );
        } else {
            $this->form()->define('uses_hibernation', fn ($resolver) => $resolver);
        }

        if ($this->form()->boolean('uses_hibernation')) {
            $this->form()->prompt(
                'hibernation_timeout',
                fn ($resolver) => $resolver->fromInput(fn (?string $value) => text(
                    label: 'Minutes idle before hibernating',
                    default: $value ?? '',
                    required: true,
                    validate: fn ($value) => $this->validateHibernationTimeout($value),
                )),
            );
        }

        $this->form()->define('cluster_id', fn ($resolver) => $resolver);

        return spin(
            fn () => $this->client->caches()->create(
                new CreateCacheRequestData(
                    type: $this->form()->get('type'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    size: $this->form()->get('size'),
                    autoUpgradeEnabled: $this->form()->boolean('auto_upgrade_enabled') ?? false,
                    isPublic: $this->form()->boolean('is_public'),
                    evictionPolicy: $this->form()->get('eviction_policy'),
                    usesHibernation: $this->form()->boolean('uses_hibernation'),
                    hibernationTimeout: $this->form()->boolean('uses_hibernation') ? $this->form()->integer('hibernation_timeout') : null,
                    clusterId: $this->form()->get('cluster_id'),
                ),
            ),
            'Creating cache...',
        );
    }
}
