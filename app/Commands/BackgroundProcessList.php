<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class BackgroundProcessList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'background-process:list {instance : The instance ID} {--json : Output as JSON}';

    protected $description = 'List all background processes for an instance';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Listing background processes');

        $processes = spin(
            fn () => $this->client->listBackgroundProcesses($this->argument('instance')),
            'Fetching background processes...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($process) => [
                    'id' => $process->id,
                    'command' => $process->command,
                    'type' => $process->type,
                    'instances' => $process->instances,
                    'created_at' => $process->createdAt?->toIso8601String(),
                ], $processes->data),
                'links' => $processes->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($processes->data) === 0) {
            info('No background processes found.');

            return;
        }

        table(
            ['ID', 'Command', 'Type', 'Instances'],
            collect($processes->data)->map(fn ($process) => [
                $process->id,
                substr($process->command, 0, 50),
                $process->type,
                $process->instances,
            ])->toArray()
        );
    }
}
