<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DatabaseList extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'database:list {--json : Output as JSON}';

    protected $description = 'List all database clusters';

    public function handle()
    {
        $this->ensureClient();

        intro('Listing database clusters');

        $databases = spin(
            fn () => $this->client->listDatabases(),
            'Fetching databases...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($db) => [
                    'id' => $db->id,
                    'name' => $db->name,
                    'type' => $db->type,
                    'status' => $db->status,
                    'region' => $db->region,
                    'schemas' => $db->schemas,
                    'created_at' => $db->createdAt?->toIso8601String(),
                ], $databases->data),
                'links' => $databases->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($databases->data) === 0) {
            $this->info('No databases found.');

            return;
        }

        table(
            ['ID', 'Name', 'Type', 'Status', 'Region', 'Schemas'],
            collect($databases->data)->map(fn ($db) => [
                $db->id,
                $db->name,
                $db->type,
                $db->status,
                $db->region,
                count($db->schemas),
            ])->toArray()
        );
    }
}
