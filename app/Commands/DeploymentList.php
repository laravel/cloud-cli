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

        intro('Deployments');

        $deployments = spin(
            fn () => $this->client->deployments()->list($this->argument('environment')),
            'Fetching deployments...',
        );

        $items = $deployments->collect();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            info('No deployments found.');

            return;
        }

        table(
            ['ID', 'Status', 'Branch', 'Commit', 'Started'],
            $items->map(fn ($deployment) => [
                $deployment->id,
                $deployment->status->label(),
                $deployment->branchName,
                $deployment->commitHash ? substr($deployment->commitHash, 0, 7) : 'N/A',
                $deployment->startedAt?->format('Y-m-d H:i:s') ?? 'N/A',
            ])->toArray(),
        );
    }
}
