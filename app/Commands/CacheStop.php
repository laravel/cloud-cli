<?php

namespace App\Commands;

use App\Dto\Cache;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheStop extends BaseCommand
{
    protected ?string $jsonDataClass = Cache::class;

    protected $signature = 'cache:stop
                            {cache? : The cache ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Stop a cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Stopping Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $this->confirmDestructive("Stop cache '{$cache->name}'?");

        $stopped = spin(
            fn () => $this->client->caches()->stop($cache->id),
            'Stopping cache...',
        );

        $this->outputJsonIfWanted($stopped);

        success("Cache '{$stopped->name}' stopped.");
    }
}
