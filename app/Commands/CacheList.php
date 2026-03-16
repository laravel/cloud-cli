<?php

namespace App\Commands;

use App\Dto\Cache;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheList extends BaseCommand
{
    protected $signature = 'cache:list {--json : Output as JSON}';

    protected $description = 'List all caches';

    public function handle()
    {
        $this->ensureClient();

        intro('Caches');

        answered('Organization', $this->client->meta()->organization()->name);

        $caches = spin(
            fn () => $this->client->caches()->list()->collect(),
            'Fetching caches...',
        );

        $items = collect($caches);

        if ($items->isEmpty()) {
            $this->failAndExit('No caches found.');
        }

        $this->outputJsonIfWanted($items);

        dataTable(
            headers: ['ID', 'Name', 'Type', 'Status', 'Region', 'Size'],
            rows: $items->map(fn (Cache $cache) => [
                $cache->id,
                $cache->name,
                $cache->type,
                $cache->status,
                $cache->region,
                $cache->size,
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('cache:get', ['cache' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
