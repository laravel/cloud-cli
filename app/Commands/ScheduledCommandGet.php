<?php

namespace App\Commands;

use App\Dto\ScheduledCommand;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;

class ScheduledCommandGet extends BaseCommand
{
    protected ?string $jsonDataClass = ScheduledCommand::class;

    protected $signature = 'scheduled-command:get {scheduled-command? : The scheduled command ID}';

    protected $description = 'Get scheduled command details';

    public function handle()
    {
        $this->ensureClient();

        intro('Scheduled Command Details');

        $scheduledCommand = $this->resolvers()->scheduledCommand()->from($this->argument('scheduled-command'));

        $this->outputJsonIfWanted($scheduledCommand);

        info("Scheduled Command: {$scheduledCommand->id}");

        dataList([
            'ID' => $scheduledCommand->id,
            'Name' => $scheduledCommand->name ?? '—',
            'Command' => $scheduledCommand->command,
            'Schedule (UTC)' => $scheduledCommand->schedule,
            'Created At' => $scheduledCommand->createdAt?->format('Y-m-d H:i:s') ?? '—',
            'Instance ID' => $scheduledCommand->instanceId ?? '—',
        ]);
    }
}
