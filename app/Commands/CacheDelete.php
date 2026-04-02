<?php

namespace App\Commands;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class CacheDelete extends BaseCommand
{
    protected $signature = 'cache:delete
                            {cache? : The cache ID or name}
                            {--force : Skip confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Delete a cache';

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Cache');

        $cache = $this->resolvers()->cache()->from($this->argument('cache'));

        if (! $this->option('force') && ! confirm("Delete cache '{$cache->name}'?", default: false)) {
            error('Cancelled');

            return self::FAILURE;
        }

        spin(
            fn () => $this->client->caches()->delete($cache->id),
            'Deleting cache...',
        );

        $this->outputJsonIfWanted('Cache deleted.');

        success('Cache deleted');
    }
}
