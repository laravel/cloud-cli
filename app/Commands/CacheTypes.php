<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class CacheTypes extends BaseCommand
{
    protected $signature = 'cache:types {--json : Output as JSON}';

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

        $rows = collect($types)->map(fn ($type) => [
            $type['type'] ?? $type['id'] ?? '-',
            $type['label'] ?? $type['name'] ?? '-',
        ])->toArray();

        dataTable(
            headers: ['Type', 'Label'],
            rows: $rows,
        );
    }
}
