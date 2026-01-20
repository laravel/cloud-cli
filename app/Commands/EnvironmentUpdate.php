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

class EnvironmentUpdate extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'environment:update
                            {environment : The environment ID}
                            {--branch= : Git branch}
                            {--build-command= : Build command}
                            {--deploy-command= : Deploy command}
                            {--json : Output as JSON}';

    protected $description = 'Update an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating environment');

        $data = [];

        if ($this->option('branch')) {
            $data['branch'] = $this->option('branch');
        }

        if ($this->option('build-command')) {
            $data['build_command'] = $this->option('build-command');
        }

        if ($this->option('deploy-command')) {
            $data['deploy_command'] = $this->option('deploy-command');
        }

        if (empty($data)) {
            error('No fields to update. Provide at least one option.');

            return 1;
        }

        try {
            $environment = spin(
                fn () => $this->client->updateEnvironment($this->argument('environment'), $data),
                'Updating environment...'
            );

            if ($this->option('json')) {
                $this->line(json_encode([
                    'id' => $environment->id,
                    'name' => $environment->name,
                    'updated_at' => $environment->updatedAt?->toIso8601String(),
                ], JSON_PRETTY_PRINT));

                return;
            }

            outro("Environment updated: {$environment->name}");
        } catch (RequestException $e) {
            if ($e->response?->status() === 422) {
                $errors = $e->response->json()['errors'] ?? [];
                foreach ($errors as $field => $messages) {
                    error(ucwords($field).': '.implode(', ', $messages));
                }
            } else {
                error('Failed to update environment: '.$e->getMessage());
            }

            return 1;
        }
    }
}
