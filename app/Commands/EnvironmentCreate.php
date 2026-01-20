<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\Validates;
use App\Dto\ValidationErrors;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class EnvironmentCreate extends Command
{
    use Colors;
    use HasAClient;
    use Validates;

    protected $signature = 'environment:create
                            {application : The application ID}
                            {--name= : Environment name}
                            {--branch= : Git branch}
                            {--json : Output as JSON}';

    protected $description = 'Create a new environment';

    protected ?string $environmentName = null;

    protected ?string $branch = null;

    public function handle()
    {
        $this->ensureClient();

        intro('Creating environment');

        $applicationId = $this->argument('application');

        $environment = $this->loopUntilValid(
            fn (ValidationErrors $errors) => $this->createEnvironment($applicationId, $errors)
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $environment->id,
                'name' => $environment->name,
                'branch' => $environment->branch,
                'created_at' => $environment->createdAt?->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        outro("Environment created: {$environment->name}");
    }

    protected function createEnvironment(string $applicationId, ValidationErrors $errors)
    {
        if (! $this->environmentName || $errors->has('name')) {
            $this->environmentName = $this->option('name') ?: text(
                label: 'Environment name',
                required: true,
            );
        }

        if (! $this->branch || $errors->has('branch')) {
            $this->branch = $this->option('branch') ?: text(
                label: 'Git branch',
                required: false,
            );
        }

        return spin(
            fn () => $this->client->createEnvironment(
                $applicationId,
                $this->environmentName,
                $this->branch ?: null
            ),
            'Creating environment...'
        );
    }
}
