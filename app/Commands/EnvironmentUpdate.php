<?php

namespace App\Commands;

use App\Client\Requests\UpdateEnvironmentRequestData;
use Illuminate\Http\Client\RequestException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class EnvironmentUpdate extends BaseCommand
{
    protected $signature = 'environment:update
                            {environment? : The environment ID or name}
                            {--branch= : Git branch}
                            {--build-command= : Build command}
                            {--deploy-command= : Deploy command}
                            {--json : Output as JSON}';

    protected $description = 'Update an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Environment');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $branch = $this->option('branch') ? (string) $this->option('branch') : null;
        $buildCommand = $this->option('build-command') ? (string) $this->option('build-command') : null;
        $deployCommand = $this->option('deploy-command') ? (string) $this->option('deploy-command') : null;

        if ($branch === null && $buildCommand === null && $deployCommand === null) {
            error('No fields to update. Provide at least one option.');

            return self::FAILURE;
        }

        try {
            $environment = spin(
                fn () => $this->client->environments()->update(new UpdateEnvironmentRequestData(
                    environmentId: $environment->id,
                    branch: $branch,
                    buildCommand: $buildCommand,
                    deployCommand: $deployCommand,
                )),
                'Updating environment...',
            );

            $this->outputJsonIfWanted([
                'id' => $environment->id,
                'name' => $environment->name,
                'updated_at' => $environment->updatedAt?->toIso8601String(),
            ]);

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

            return self::FAILURE;
        }
    }
}
