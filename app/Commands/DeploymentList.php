<?php

namespace App\Commands;

use App\Concerns\FiltersLists;
use App\Dto\Deployment;
use App\Enums\DeploymentStatus;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\warning;

class DeploymentList extends BaseCommand
{
    use FiltersLists;

    protected ?string $jsonDataClass = Deployment::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'deployment:list
                            {environment? : The environment ID}
                            {--status= : Filter by status}
                            {--branch= : Filter by branch name}
                            {--commit= : Filter by commit hash}';

    protected $description = 'List all deployments for an environment';

    public function handle()
    {
        $this->ensureClient();

        $filters = [
            'status' => $this->filterOption('status', DeploymentStatus::class),
            'branchName' => $this->filterOption('branch'),
            'commitHash' => $this->filterOption('commit'),
        ];

        intro('Deployments');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $items = $this->fetchList(
            fn () => $this->client->deployments()->list($environment->id, ...$filters),
            'Fetching deployments...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No deployments found.');

            return self::SUCCESS;
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
