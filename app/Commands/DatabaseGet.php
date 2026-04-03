<?php

namespace App\Commands;

use function Laravel\Prompts\intro;

class DatabaseGet extends BaseCommand
{
    protected $signature = 'database:get
                            {cluster? : The database cluster ID or name}
                            {database? : The database schema ID or name}';

    protected $description = 'Get database (schema) details';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Details');

        $database = $this->resolvers()->database()->withCluster($this->argument('cluster'))->from($this->argument('database'));

        $this->outputJsonIfWanted([
            'id' => $database->id,
            'name' => $database->name,
            'created_at' => $database->createdAt?->toIso8601String(),
        ]);

        dataList([
            'ID' => $database->id,
            'Name' => $database->name,
            'Created At' => $database->createdAt?->toIso8601String() ?? '—',
        ]);
    }
}
