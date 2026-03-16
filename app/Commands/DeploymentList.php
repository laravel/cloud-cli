<?php

namespace App\Commands;

use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DeploymentList extends BaseCommand
{
    protected $signature = 'deployment:list {environment? : The environment ID} {--json : Output as JSON}';

    protected $description = 'List all deployments for an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Deployments');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $deployments = spin(
            fn () => $this->client->deployments()->list($environment->id),
            'Fetching deployments...',
        );

        $items = $deployments->collect();

        if ($items->isEmpty()) {
            $this->failAndExit('No deployments found.');
        }

        $this->outputJsonIfWanted($items);

        dataTable(
            headers: ['ID', 'Status', 'Branch', 'Commit', 'Started'],
            rows: $items->map(fn ($deployment) => [
                $deployment->id,
                $deployment->status->label(),
                $deployment->branchName,
                $deployment->commitHash ? substr($deployment->commitHash, 0, 7) : 'N/A',
                $deployment->startedAt?->format('Y-m-d H:i:s') ?? 'N/A',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $this->call('deployment:get', ['deployment' => $row[0]]),
                    'View',
                ],
            ],
        );
    }
}
