<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class CommandList extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'command:list {environment : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all commands for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Listing commands');

        $commands = spin(
            fn () => $this->client->listCommands($this->argument('environment')),
            'Fetching commands...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($cmd) => [
                    'id' => $cmd->id,
                    'command' => $cmd->command,
                    'status' => $cmd->status,
                    'exit_code' => $cmd->exitCode,
                    'started_at' => $cmd->startedAt?->toIso8601String(),
                    'finished_at' => $cmd->finishedAt?->toIso8601String(),
                ], $commands->data),
                'links' => $commands->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($commands->data) === 0) {
            $this->info('No commands found.');

            return;
        }

        table(
            ['ID', 'Command', 'Status', 'Exit Code', 'Started'],
            collect($commands->data)->map(fn ($cmd) => [
                $cmd->id,
                substr($cmd->command, 0, 50),
                $cmd->status,
                $cmd->exitCode ?? 'N/A',
                $cmd->startedAt?->format('Y-m-d H:i:s') ?? 'N/A',
            ])->toArray()
        );
    }
}
