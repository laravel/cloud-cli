<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Support\Facades\Process;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;

class CommandList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'command:list {environment? : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all commands for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Commands');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $commands = $this->client->commands()->list($environment->id)->collect();

        $this->outputJsonIfWanted($commands);

        dataTable(
            ['ID', 'Command', 'Status', 'Exit Code', 'Started'],
            $commands->map(fn ($cmd) => [
                $cmd->id,
                substr($cmd->command, 0, 50),
                $cmd->status->label(),
                $cmd->exitCode ?? '—',
                $cmd->startedAt?->format('Y-m-d H:i:s') ?? '—',
            ])->toArray(),
            [
                Key::ENTER => [
                    // TODO: Correct url...
                    fn ($row) => Process::run('open '.$environment->url),
                    'Open in browser',
                ],
                'o' => [
                    fn ($row) => $this->call('command:get', ['commandId' => $row[0]]),
                    'Open in terminal',
                ],
            ],
        );
    }
}
