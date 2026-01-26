<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class BackgroundProcessGet extends BaseCommand
{
    use HasAClient;

    protected $signature = 'background-process:get {process : The background process ID} {--json : Output as JSON}';

    protected $description = 'Get background process details';

    public function handle()
    {
        $this->ensureClient();

        intro('Background Process Details');

        $process = spin(
            fn () => $this->client->getBackgroundProcess($this->argument('process')),
            'Fetching background process...',
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $process->id,
                'command' => $process->command,
                'type' => $process->type,
                'instances' => $process->instances,
                'queue' => $process->queue,
                'connection' => $process->connection,
                'timeout' => $process->timeout,
                'sleep' => $process->sleep,
                'tries' => $process->tries,
                'max_processes' => $process->maxProcesses,
                'min_processes' => $process->minProcesses,
                'created_at' => $process->createdAt?->toIso8601String(),
                'updated_at' => $process->updatedAt?->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        info("Background Process: {$process->id}");
        $this->line("Command: {$process->command}");
        $this->line("Type: {$process->type}");
        $this->line("Instances: {$process->instances}");

        if ($process->queue) {
            $this->line("Queue: {$process->queue}");
        }
    }
}
