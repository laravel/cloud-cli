<?php

namespace App\Commands;

use App\Concerns\DeterminesDefaultRegion;
use App\Concerns\Validates;
use App\Dto\Region;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class CacheCreate extends BaseCommand
{
    use DeterminesDefaultRegion;
    use Validates;

    protected $signature = 'cache:create
                            {--name= : Cache name}
                            {--type= : Cache type}
                            {--region= : Cache region}
                            {--json : Output as JSON}';

    protected $description = 'Create a new cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Cache');

        $cache = $this->loopUntilValid($this->createCache(...));

        $this->outputJsonIfWanted($cache);

        success('Cache created');

        outro("Cache created: {$cache->name}");
    }

    protected function createCache()
    {
        $types = spin(
            fn () => $this->client->caches()->types(),
            'Fetching cache types...',
        );

        $typeOptions = collect($types)->mapWithKeys(fn ($type) => [
            $type['type'] ?? $type['id'] ?? '' => $type['label'] ?? $type['name'] ?? $type['type'] ?? $type['id'] ?? '',
        ])->filter()->toArray();

        $this->addParam(
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

        $this->addParam(
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

        $this->addParam(
            'region',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Region',
                    options: collect($regions)->mapWithKeys(fn (Region $region) => [
                        $region->value => $region->label,
                    ]),
                    default: $value ?? $this->getDefaultRegion(),
                    required: true,
                ))
                ->nonInteractively(fn () => $this->getDefaultRegion()),
        );

        return spin(
            fn () => $this->client->caches()->create(
                $this->getParam('type'),
                $this->getParam('name'),
                $this->getParam('region'),
                [],
            ),
            'Creating cache...',
        );
    }
}
