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

class BackgroundProcessUpdate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'background-process:update
                            {process : The background process ID}
                            {--command= : The command to run}
                            {--instances= : Number of instances}
                            {--json : Output as JSON}';

    protected $description = 'Update a background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating background process');

        $data = [];

        if ($this->option('command')) {
            $data['command'] = $this->option('command');
        }

        if ($this->option('instances')) {
            $data['instances'] = (int) $this->option('instances');
        }

        if (empty($data)) {
            error('No fields to update. Provide at least one option.');

            return 1;
        }

        try {
            $process = spin(
                fn () => $this->client->updateBackgroundProcess($this->argument('process'), $data),
                'Updating background process...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $process->id,
                    'command' => $process->command,
                    'instances' => $process->instances,
                    'updated_at' => $process->updatedAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Background process updated: {$process->id}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to update background process: '.$e->getMessage());
            }

            return 1;
        }
    }
}
