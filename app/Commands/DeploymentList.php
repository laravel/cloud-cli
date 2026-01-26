<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class DeploymentList extends BaseCommand
{
    use HasAClient;

    protected $signature = 'deployment:list {environment : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all deployments for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Listing Deployments');

        $deployments = spin(
            fn () => $this->client->listDeployments($this->argument('environment')),
            'Fetching deployments...',
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'data' => array_map(fn ($deployment) => [
                    'id' => $deployment->id,
                    'status' => $deployment->status->value,
                    'branch' => $deployment->branchName,
                    'commit_hash' => $deployment->commitHash,
                    'commit_message' => $deployment->commitMessage,
                    'started_at' => $deployment->startedAt?->toIso8601String(),
                    'finished_at' => $deployment->finishedAt?->toIso8601String(),
                ], $deployments->data),
                'links' => $deployments->links,
            ], JSON_PRETTY_PRINT));

            return;
        }

        if (count($deployments->data) === 0) {
            info('No deployments found.');

            return;
        }

        table(
            ['ID', 'Status', 'Branch', 'Commit', 'Started'],
            collect($deployments->data)->map(fn ($deployment) => [
                $deployment->id,
                $deployment->status->label(),
                $deployment->branchName,
                $deployment->commitHash ? substr($deployment->commitHash, 0, 7) : 'N/A',
                $deployment->startedAt?->format('Y-m-d H:i:s') ?? 'N/A',
            ])->toArray(),
        );
    }
}
