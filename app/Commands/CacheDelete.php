<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheDelete extends BaseCommand
{
    protected $signature = 'cache:delete
                            {cache? : The cache ID or name}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        $this->confirmDestructive("Delete cache '{$cache->name}'?");

        spin(
            fn () => $this->client->caches()->delete($cache->id),
            'Deleting cache...',
        );

        $this->outputJsonIfWanted('Cache deleted.');

        success('Cache deleted');
    }
}
