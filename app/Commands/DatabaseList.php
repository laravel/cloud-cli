<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
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

        if (! $this->option('json')) {
            intro('Database Clusters');
        }

        $databases = spin(
            fn () => $this->client->listDatabases(),
            'Fetching databases...'
        );

        if ($this->option('json')) {
            $this->line($databases->toJson());

            return;
        }

        if (count($databases->data) === 0) {
            info('No databases found.');

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
                collect($db->schemas)->pluck('name')->implode(PHP_EOL),
            ])->toArray()
        );
    }
}
