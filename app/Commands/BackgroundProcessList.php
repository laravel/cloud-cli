<?php

namespace App\Commands;

use App\Dto\BackgroundProcess;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class BackgroundProcessList extends BaseCommand
{
    protected ?string $jsonDataClass = BackgroundProcess::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'background-process:list {instance? : The instance ID}';

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

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No background processes found.');

            return self::FAILURE;
        }

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
