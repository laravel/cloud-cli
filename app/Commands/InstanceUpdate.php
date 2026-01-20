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

class InstanceUpdate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'instance:update
                            {instance : The instance ID}
                            {--size= : Instance size}
                            {--min-replicas= : Minimum replicas}
                            {--max-replicas= : Maximum replicas}
                            {--scaling-type= : Scaling type}
                            {--json : Output as JSON}';

    protected $description = 'Update an instance';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating instance');

        $data = [];

        if ($this->option('size')) {
            $data['size'] = $this->option('size');
        }

        if ($this->option('min-replicas')) {
            $data['min_replicas'] = (int) $this->option('min-replicas');
        }

        if ($this->option('max-replicas')) {
            $data['max_replicas'] = (int) $this->option('max-replicas');
        }

        if ($this->option('scaling-type')) {
            $data['scaling_type'] = $this->option('scaling-type');
        }

        if (empty($data)) {
            error('No fields to update. Provide at least one option.');

            return 1;
        }

        try {
            $instance = spin(
                fn () => $this->client->updateInstance($this->argument('instance'), $data),
                'Updating instance...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $instance->id,
                    'name' => $instance->name,
                    'updated_at' => $instance->updatedAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Instance updated: {$instance->name}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to update instance: '.$e->getMessage());
            }

            return 1;
        }
    }
}
