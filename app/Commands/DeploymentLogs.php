<?php

namespace App\Commands;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DeploymentLogs extends BaseCommand
{
    protected $signature = 'deployment:logs
                            {deployment? : The deployment ID}
                            {--json : Output as JSON}';

    protected $description = 'View deployment build and deploy logs';

    public function handle()
    {
        $this->ensureClient();

        intro('Deployment Logs');

        $deployment = $this->resolvers()->deployment()->from($this->argument('deployment'));

        $logs = spin(
            fn () => $this->client->deployments()->logs($deployment->id),
            'Fetching deployment logs...',
        );

        $this->outputJsonIfWanted($logs);

        $this->displayLogs($logs);
    }

    protected function displayLogs(array $logs): void
    {
        $data = $logs['data'] ?? [];
        $meta = $logs['meta'] ?? [];

        if (! empty($meta['deployment_status'])) {
            info("Deployment status: {$meta['deployment_status']}");
        }

        $this->displayPhase('Build', $data['build'] ?? []);
        $this->displayPhase('Deploy', $data['deploy'] ?? []);
    }

    protected function displayPhase(string $name, array $phase): void
    {
        if (empty($phase) || ! ($phase['available'] ?? false)) {
            warning("{$name} logs are not available.");

            return;
        }

        info($name.' Logs');

        foreach ($phase['steps'] ?? [] as $step) {
            $status = $step['status'] ?? 'unknown';
            $description = $step['description'] ?? $step['step'] ?? 'Unknown step';
            $duration = isset($step['duration_ms']) ? ' ('.number_format($step['duration_ms'] / 1000, 2).'s)' : '';

            $this->line("  [{$status}] {$description}{$duration}");

            if (! empty($step['output'])) {
                foreach (explode("\n", $step['output']) as $outputLine) {
                    $this->line("    {$outputLine}");
                }
            }
        }
    }
}
