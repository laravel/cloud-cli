<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BackgroundProcessList extends BaseCommand
{
    protected $signature = 'background-process:list {instance? : The instance ID} {--json : Output as JSON}';

    protected $description = 'List all background processes for an instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Background Processes');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $processes = spin(
            fn () => $this->client->backgroundProcesses()->list($instance->id),
            'Fetching background processes...',
        );

        $items = $processes->collect();

        if ($items->isEmpty()) {
            $this->failAndExit('No background processes found.');
        }

        $this->outputJsonIfWanted($items);

        dataTable(
            headers: ['ID', 'Command', 'Type', 'Processes'],
            rows: $items->map(fn ($process) => [
                $process->id,
                str($process->command)->limit(25)->toString(),
                $process->type,
                $process->processes,
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('background-process:get', ['process' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
