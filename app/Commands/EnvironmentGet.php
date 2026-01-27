<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentGet extends BaseCommand
{
    use HasAClient;

    protected $signature = 'environment:get {environment : The environment ID} {--json : Output as JSON}';

    protected $description = 'Get environment details';

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Details');

        $environment = spin(
            fn () => $this->client->environments()->include('instances', 'currentDeployment')->get($this->argument('environment')),
            'Fetching environment...',
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $environment->id,
                'name' => $environment->name,
                'branch' => $environment->branch,
                'status' => $environment->status,
                'url' => $environment->url,
                'vanity_domain' => $environment->vanityDomain,
                'php_version' => $environment->phpMajorVersion,
                'node_version' => $environment->nodeVersion,
                'uses_octane' => $environment->usesOctane,
                'uses_hibernation' => $environment->usesHibernation,
                'build_command' => $environment->buildCommand,
                'deploy_command' => $environment->deployCommand,
                'instance_ids' => $environment->instances,
                'current_deployment_id' => $environment->currentDeploymentId,
                'created_at' => $environment->createdAt?->toIso8601String(),
                'updated_at' => $environment->updatedAt?->toIso8601String(),
            ], JSON_PRETTY_PRINT));

            return;
        }

        info("Environment: {$environment->name}");
        $this->line("ID: {$environment->id}");
        $branch = $environment->branch ?? 'N/A';
        $this->line("Branch: {$branch}");
        $this->line("Status: {$environment->status}");
        $this->line("URL: {$environment->url}");
        $this->line("PHP Version: {$environment->phpMajorVersion}");
        $this->line('Instances: '.count($environment->instances));
    }
}
