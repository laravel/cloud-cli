<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\DatabaseCluster;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class DatabaseClusterList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = DatabaseCluster::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'database-cluster:list
                            {--type= : Filter by type (laravel_mysql|aws_rds_mysql|aws_rds_postgres|neon_serverless_postgres)}
                            {--region= : Filter by region}
                            {--status= : Filter by status}';

    protected $description = 'List all database clusters';

    protected $aliases = ['db-cluster:list'];

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'type' => $this->filterOption('type', ['laravel_mysql', 'aws_rds_mysql', 'aws_rds_postgres', 'neon_serverless_postgres']),
            'region' => $this->filterOption('region'),
            'status' => $this->filterOption('status', ['creating', 'updating', 'restarting', 'upgrading', 'moving', 'available', 'stopped', 'restoring', 'restore_failed', 'disabled', 'snapshotting_before_archiving', 'archiving', 'archived', 'deleting', 'deleted', 'unknown']),
        ];

        intro('Database Clusters');

        $items = $this->fetchList(
            fn () => $this->client->databaseClusters()->include('databases')->list(...$filters),
            'Fetching databases...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No databases found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Type', 'Status', 'Region', 'Schemas'],
            rows: $items->map(fn ($db) => [
                $db->id,
                $db->name,
                $db->type,
                $db->status,
                $db->region,
                collect($db->schemas)->pluck('name')->implode(PHP_EOL),
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('database-cluster:get', ['database' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
