<?php

namespace App\Commands;

use App\Dto\Deployment;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DeploymentList extends BaseCommand
{
    protected ?string $jsonDataClass = Deployment::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'deployment:list {environment? : The environment ID}';

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

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No deployments found.');

            return self::FAILURE;
        }

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
