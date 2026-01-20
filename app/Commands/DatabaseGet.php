<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DatabaseGet extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'database:get {database : The database ID} {--json : Output as JSON}';

    protected $description = 'Get database cluster details';

    public function handle()
    {
        $this->ensureClient();

        intro('Database Cluster Details');

        $database = spin(
            fn () => $this->client->getDatabase($this->argument('database')),
            'Fetching database...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $database->id,
                'name' => $database->name,
                'type' => $database->type,
                'status' => $database->status,
                'region' => $database->region,
                'config' => $database->config,
                'connection' => $database->connection,
                'schemas' => $database->schemas,
                'created_at' => $database->createdAt?->toIso8601String(),
                'updated_at' => $database->updatedAt?->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        $this->info("Database: {$database->name}");
        $this->line("ID: {$database->id}");
        $this->line("Type: {$database->type}");
        $this->line("Status: {$database->status}");
        $this->line("Region: {$database->region}");
        $this->line('Schemas: '.count($database->schemas));
    }
}
