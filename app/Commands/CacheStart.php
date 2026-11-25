<?php

namespace App\Commands;

use App\Dto\Cache;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheStart extends BaseCommand
{
    protected ?string $jsonDataClass = Cache::class;

    protected $signature = 'cache:start {cache? : The cache ID or name}';

    protected $description = 'Start a stopped cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Starting Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $started = spin(
            fn () => $this->client->caches()->start($cache->id),
            'Starting cache...',
        );

        $this->outputJsonIfWanted($started);

        success("Cache '{$started->name}' started.");
    }
}
