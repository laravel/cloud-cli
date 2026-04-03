<?php

namespace App\Commands;

use App\Dto\Cache;

use function Laravel\Prompts\intro;

class CacheGet extends BaseCommand
{
    protected ?string $jsonDataClass = Cache::class;

    protected $signature = 'cache:get {cache? : The cache ID or name}';

    protected $description = 'Get cache details';

    public function handle()
    {
        $this->ensureClient();

        intro('Cache Details');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $this->outputJsonIfWanted($cache);

        dataList([
            'Name' => $cache->name,
            'ID' => $cache->id,
            'Type' => $cache->type,
            'Status' => $cache->status,
            'Region' => $cache->region,
            'Size' => $cache->size,
            'Auto upgrade' => $cache->autoUpgradeEnabled ? 'Yes' : 'No',
            'Public' => $cache->isPublic ? 'Yes' : 'No',
            'Created At' => $cache->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
