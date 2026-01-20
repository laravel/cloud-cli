<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Illuminate\Http\Client\RequestException;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class CommandRun extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'command:run
                            {environment : The environment ID}
                            {command : The command to run}
                            {--json : Output as JSON}';

    protected $description = 'Run a command on an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Running command');

        try {
            $cmd = spin(
                fn () => $this->client->runCommand(
                    $this->argument('environment'),
                    $this->argument('command')
                ),
                'Running command...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $cmd->id,
                    'command' => $cmd->command,
                    'status' => $cmd->status,
                    'started_at' => $cmd->startedAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Command started: {$cmd->id}\nUse 'command:get {$cmd->id}' to check status and output");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to run command: '.$e->getMessage());
            }

            return 1;
        }
    }
}
