<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
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

        intro('Background Processes');

        $processes = spin(
            fn () => $this->client->backgroundProcesses()->list($this->argument('instance')),
            'Fetching background processes...',
        );

        $items = $processes->collect();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            info('No background processes found.');

            return;
        }

        table(
            ['ID', 'Command', 'Type', 'Instances'],
            $items->map(fn ($process) => [
                $process->id,
                substr($process->command, 0, 50),
                $process->type,
                $process->instances,
            ])->toArray(),
        );
    }
}
