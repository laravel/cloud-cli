<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Cache;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class CacheList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Cache::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'cache:list
                            {--type= : Filter by type (upstash_redis|laravel_valkey|aws_elasticache_redis|aws_elasticache_valkey)}
                            {--region= : Filter by region}
                            {--status= : Filter by status (creating|updating|available|stopped|deleting|deleted|unknown)}';

    protected $description = 'List all caches';

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'type' => $this->filterOption('type', ['upstash_redis', 'laravel_valkey', 'aws_elasticache_redis', 'aws_elasticache_valkey']),
            'region' => $this->filterOption('region'),
            'status' => $this->filterOption('status', ['creating', 'updating', 'available', 'stopped', 'deleting', 'deleted', 'unknown']),
        ];

        intro('Caches');

        answered('Organization', $this->client->meta()->organization()->name);

        $items = $this->fetchList(
            fn () => $this->client->caches()->list(...$filters),
            'Fetching caches...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No caches found.');

            return self::SUCCESS;
        }

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
