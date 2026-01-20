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

class BackgroundProcessCreate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'background-process:create
                            {instance : The instance ID}
                            {--command= : The command to run}
                            {--type= : Process type (queue|custom)}
                            {--instances= : Number of instances}
                            {--queue= : Queue name (for queue type)}
                            {--connection= : Queue connection (for queue type)}
                            {--json : Output as JSON}';

    protected $description = 'Create a new background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating background process');

        $data = [];

        if ($this->option('command')) {
            $data['command'] = $this->option('command');
        }

        if ($this->option('type')) {
            $data['type'] = $this->option('type');
        }

        if ($this->option('instances')) {
            $data['instances'] = (int) $this->option('instances');
        }

        if ($this->option('queue')) {
            $data['queue'] = $this->option('queue');
        }

        if ($this->option('connection')) {
            $data['connection'] = $this->option('connection');
        }

        try {
            $process = spin(
                fn () => $this->client->createBackgroundProcess($this->argument('instance'), $data),
                'Creating background process...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $process->id,
                    'command' => $process->command,
                    'type' => $process->type,
                    'created_at' => $process->createdAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Background process created: {$process->id}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to create background process: '.$e->getMessage());
            }

            return 1;
        }
    }
}
