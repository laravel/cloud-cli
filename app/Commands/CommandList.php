<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Command;
use App\Enums\CommandStatus;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;

class CommandList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Command::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'command:list
                            {environment? : The environment ID}
                            {--status= : Filter by status (pending|command.created|command.running|command.failure|command.success)}
                            {--command= : Filter by command}';

    protected $description = 'List all commands for an environment';

    protected $aliases = ['cmd:list'];

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'status' => $this->filterOption('status', CommandStatus::class),
            'command' => $this->filterOption('command'),
        ];

        intro('Commands');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $commands = $this->fetchList(
            fn () => $this->client->commands()->list($environment->id, ...$filters),
            'Fetching commands...',
        );

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
