<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class CommandGet extends BaseCommand
{
    use HasAClient;

    protected $signature = 'command:get {command : The command ID} {--json : Output as JSON}';

    protected $description = 'Get command details';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Command Details');

        $cmd = spin(
            fn () => $this->client->getCommand($this->argument('command')),
            'Fetching command...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $cmd->id,
                'command' => $cmd->command,
                'status' => $cmd->status,
                'output' => $cmd->output,
                'exit_code' => $cmd->exitCode,
                'started_at' => $cmd->startedAt?->toIso8601String(),
                'finished_at' => $cmd->finishedAt?->toIso8601String(),
                'environment_id' => $cmd->environmentId,
                'instance_id' => $cmd->instanceId,
            ], JSON_PRETTY_PRINT));

            return;
        }

        info("Command: {$cmd->command}");
        $this->line("ID: {$cmd->id}");
        $this->line("Status: {$cmd->status}");
        $this->line('Exit Code: '.($cmd->exitCode ?? 'N/A'));

        if ($cmd->output) {
            $this->newLine();
            $this->line('Output:');
            $this->line($cmd->output);
        }
    }
}
