<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresDatabaseCluster;

class DatabaseGet extends BaseCommand
{
    use HasAClient;
    use RequiresDatabaseCluster;

    protected $signature = 'database:get {database? : The database ID or name} {--json : Output as JSON}';

    protected $description = 'Get database cluster details';

    public function handle()
    {
        $this->ensureClient();

        if (! $this->option('json')) {
            if ($this->argument('database')) {
                $this->intro('Database Cluster Details: '.$this->argument('database'));
            } else {
                $this->intro('Database Cluster Details');
            }
        }

        $database = $this->getDatabaseCluster(showPrompt: false);

        if ($this->option('json')) {
            $this->line($database->toJson());

            return;
        }

        dataList([
            'ID' => $database->id,
            'Name' => $database->name,
            'Type' => $database->type,
            'Status' => $database->status,
            'Region' => $database->region,
            'Schemas' => collect($database->schemas)->pluck('name')->toArray(),
        ]);
    }
}
