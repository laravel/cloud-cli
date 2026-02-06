<?php

namespace App\Commands;

use App\Client\Requests\UpdateInstanceRequestData;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class InstanceUpdate extends BaseCommand
{
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

        intro('Updating Instance');

        $size = $this->option('size') ? (string) $this->option('size') : null;
        $minReplicas = $this->option('min-replicas') !== null ? (int) $this->option('min-replicas') : null;
        $maxReplicas = $this->option('max-replicas') !== null ? (int) $this->option('max-replicas') : null;
        $scalingType = $this->option('scaling-type') ? (string) $this->option('scaling-type') : null;

        if ($size === null && $minReplicas === null && $maxReplicas === null && $scalingType === null) {
            error('No fields to update. Provide at least one option.');

            return 1;
        }

        try {
            $instance = spin(
                fn () => $this->client->instances()->update(new UpdateInstanceRequestData(
                    instanceId: $this->argument('instance'),
                    size: $size,
                    minReplicas: $minReplicas,
                    maxReplicas: $maxReplicas,
                    scalingType: $scalingType,
                )),
                'Updating instance...',
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
