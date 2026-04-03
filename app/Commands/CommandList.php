<?php

namespace App\Commands;

use App\Dto\Command;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;

class CommandList extends BaseCommand
{
    protected ?string $jsonDataClass = Command::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'command:list {environment? : The environment ID}';

    protected $description = 'List all commands for an environment';

    protected $aliases = ['cmd:list'];

    public function handle()
    {
        $this->ensureClient();

        intro('Commands');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $commands = $this->client->commands()->list($environment->id)->collect();

        $this->outputJsonIfWanted($commands);

        dataTable(
            headers: ['ID', 'Command', 'Status', 'Exit Code', 'Started'],
            rows: $commands->map(fn ($cmd) => [
                $cmd->id,
                substr($cmd->command, 0, 50),
                $cmd->status->label(),
                $cmd->exitCode ?? '—',
                $cmd->startedAt?->format('Y-m-d H:i:s') ?? '—',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('command:get', ['commandId' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
