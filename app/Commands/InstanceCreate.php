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

class InstanceCreate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'instance:create
                            {environment : The environment ID}
                            {--name= : Instance name}
                            {--type= : Instance type (app|worker)}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}
                            {--json : Output as JSON}';

    protected $description = 'Create a new instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating instance');

        $data = [];

        if ($this->option('name')) {
            $data['name'] = $this->option('name');
        }

        if ($this->option('type')) {
            $data['type'] = $this->option('type');
        }

        if ($this->option('size')) {
            $data['size'] = $this->option('size');
        }

        if ($this->option('min-replicas')) {
            $data['min_replicas'] = (int) $this->option('min-replicas');
        }

        if ($this->option('max-replicas')) {
            $data['max_replicas'] = (int) $this->option('max-replicas');
        }

        try {
            $instance = spin(
                fn () => $this->client->createInstance($this->argument('environment'), $data),
                'Creating instance...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $instance->id,
                    'name' => $instance->name,
                    'type' => $instance->type,
                    'created_at' => $instance->createdAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Instance created: {$instance->name}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to create instance: '.$e->getMessage());
            }

            return 1;
        }
    }
}
