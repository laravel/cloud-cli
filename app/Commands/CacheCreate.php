<?php

namespace App\Commands;

use App\Client\Requests\CreateCacheRequestData;
use App\Concerns\DeterminesDefaultRegion;
use App\Dto\Cache;
use App\Dto\CacheType;
use App\Dto\Region;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CacheCreate extends BaseCommand
{
    protected ?string $jsonDataClass = Cache::class;

    use DeterminesDefaultRegion;

    protected $signature = 'cache:create
                            {--name= : Cache name}
                            {--type= : Cache type}
                            {--region= : Cache region}
                            {--size= : Cache size}
                            {--auto-upgrade-enabled= : Auto upgrade enabled}
                            {--is-public= : Is public}
                            {--eviction-policy= : Eviction policy}';

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
                    validate: fn ($value) => match (true) {
                        strlen($value) < 3 => 'Name must be at least 3 characters',
                        strlen($value) > 40 => 'Name must be less than 40 characters',
                        default => null,
                    },
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

        if ($type->type === 'laravel_valkey') {
            $this->form()->prompt(
                'eviction_policy',
                fn ($resolver) => $resolver->fromInput(fn (?string $value) => select(
                    label: 'Eviction policy',
                    options: [
                        'allkeys-lru' => 'All keys LRU',
                        'noeviction' => 'No eviction',
                        'volatile-lru' => 'Volatile LRU',
                        'allkeys-random' => 'All keys random',
                        'volatile-random' => 'Volatile random',
                        'volatile-ttl' => 'Volatile TTL',
                        'allkeys-lfu' => 'All keys LFU',
                        'volatile-lfu' => 'Volatile LFU',
                    ],
                    default: $value ?? 'allkeys-lru',
                )),
            );
        }

        return spin(
            fn () => $this->client->caches()->create(
                new CreateCacheRequestData(
                    type: $this->form()->get('type'),
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    size: $this->form()->get('size'),
                    autoUpgradeEnabled: $this->form()->boolean('auto_upgrade_enabled'),
                    isPublic: $this->form()->boolean('is_public'),
                    evictionPolicy: $this->form()->get('eviction_policy'),
                ),
            ),
            'Creating cache...',
        );
    }
}
