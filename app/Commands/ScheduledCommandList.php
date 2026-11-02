<?php

namespace App\Commands;

use App\Dto\ScheduledCommand;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class ScheduledCommandList extends BaseCommand
{
    protected ?string $jsonDataClass = ScheduledCommand::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'scheduled-command:list {instance? : The instance ID}';

    protected $description = 'List all scheduled commands for an instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Scheduled Commands');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $items = $this->fetchList(
            fn () => $this->client->scheduledCommands()->list($instance->id),
            'Fetching scheduled commands...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No scheduled commands found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Command', 'Schedule'],
            rows: $items->map(fn (ScheduledCommand $scheduledCommand) => [
                $scheduledCommand->id,
                $scheduledCommand->name ?? '—',
                str($scheduledCommand->command)->limit(40)->toString(),
                $scheduledCommand->schedule,
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('scheduled-command:get', ['scheduled-command' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
