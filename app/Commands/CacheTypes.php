<?php

namespace App\Commands;

use App\Dto\CacheType;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class CacheTypes extends BaseCommand
{
    protected ?string $jsonDataClass = CacheType::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'cache:types';

    protected $description = 'List available cache types';

    public function handle()
    {
        $this->ensureClient();

        intro('Cache Types');

        $types = spin(
            fn () => $this->client->caches()->types(),
            'Fetching cache types...',
        );

        $items = collect($types);

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No cache types found.');

            return self::FAILURE;
        }

        $rows = collect($types)->map(fn (CacheType $type, $index) => [
            $type->label,
            $type->supportsAutoUpgrade ? 'Yes' : 'No',
            implode(PHP_EOL, collect($type->regions)->when($index < count($types) - 1, fn ($regions) => $regions->push(''))->toArray()),
            implode(PHP_EOL, collect($type->sizes)->pluck('label')->when($index < count($types) - 1, fn ($sizes) => $sizes->push(''))->toArray()),
        ])->toArray();

        table(
            headers: ['Type', 'Auto Upgrade', 'Regions', 'Sizes'],
            rows: $rows,
        );
    }
}
