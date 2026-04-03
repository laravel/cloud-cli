<?php

namespace App\Commands;

use App\Dto\Deployment;
use App\Git;

use function Laravel\Prompts\intro;

class DeploymentGet extends BaseCommand
{
    protected ?string $jsonDataClass = Deployment::class;

    protected $signature = 'deployment:get
                            {deployment? : The deployment ID}';

    protected $description = 'Get deployment details';

    public function handle()
    {
        $this->ensureClient();

        intro('Deployment Details');

        $deployment = $this->resolvers()->deployment()->from($this->argument('deployment'));
        $environment = $this->client->environments()->include('application')->get($deployment->environment->id);

        $this->outputJsonIfWanted($deployment);

        dataList([
            'ID' => $deployment->id,
            'Status' => $deployment->status->label(),
            'Branch' => Git::branchUrl($environment->application->repositoryFullName, $deployment->branchName),
            'Commit' => Git::commitUrl($environment->application->repositoryFullName, $deployment->commitHash),
            'Message' => $deployment->commitMessage,
            'Author' => $deployment->commitAuthor ?? '—',
            'Started At' => $deployment->startedAt?->toIso8601String() ?? '—',
            'Finished At' => $deployment->finishedAt?->toIso8601String() ?? '—',
            'Duration' => $deployment->finishedAt ? $deployment->totalTime()->format('%I:%S') : '—',
            'Failure Reason' => $deployment->failureReason,
        ]);
    }
}
