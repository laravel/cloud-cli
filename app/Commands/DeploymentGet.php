<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Dto\Deployment;
use App\Dto\Environment;
use App\Git;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DeploymentGet extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;

    protected $signature = 'deployment:get'
        .' {application? : The application ID or name}'
        .' {environment? : The environment ID or name}'
        .' {deployment? : The deployment ID}'
        .' {--json : Output as JSON}';

    protected $description = 'Get deployment details';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Deployment Details');

        $application = $this->getCloudApplication();
        $environment = $this->getEnvironment(collect($application->environments));
        $deployment = $this->getDeployment($environment);

        if (! $deployment) {
            warning('No deployments found for environment '.$environment->name);

            return;
        }

        if ($this->wantsJson()) {
            $this->line($deployment->toJson());

            return;
        }

        $data = [
            'ID' => $deployment->id,
            'Status' => $deployment->status->label(),
            'Branch' => Git::branchUrl($application->repositoryFullName, $deployment->branchName),
            'Commit' => Git::commitUrl($application->repositoryFullName, $deployment->commitHash),
            'Message' => $deployment->commitMessage,
            'Author' => $deployment->commitAuthor ?? '—',
            'Started At' => $deployment->startedAt?->toIso8601String() ?? '—',
            'Finished At' => $deployment->finishedAt?->toIso8601String() ?? '—',
            'Duration' => $deployment->finishedAt ? $deployment->totalTime()->format('%I:%S') : '—',
        ];

        if ($deployment->failureReason) {
            $data['Failure Reason'] = $deployment->failureReason;
        }

        dataList($data);
    }

    protected function getDeployment(Environment $environment): ?Deployment
    {
        if ($this->argument('deployment')) {
            return spin(
                fn () => $this->client->getDeployment($this->argument('deployment')),
                'Fetching deployment...'
            );
        }

        $deployments = spin(
            fn () => $this->client->listDeployments($environment->id),
            'Fetching deployments...'
        );

        if (count($deployments->data) === 0) {
            return null;
        }

        if (count($deployments->data) === 1) {
            return $deployments->data[0];
        }

        $this->ensureInteractive('Please provide a deployment ID.');

        $selection = select(
            label: 'Deployment',
            options: collect($deployments->data)->mapWithKeys(fn ($deployment) => [
                $deployment->id => $deployment->startedAt?->toIso8601String().$this->dim(' ('.str($deployment->commitMessage)->limit(10).')'),
            ]),
        );

        return collect($deployments->data)->firstWhere('id', $selection);
    }
}
