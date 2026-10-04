<?php

namespace App\Commands;

use App\Dto\DeploymentLog;
use App\Prompts\DeploymentLogsPrompt;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DeploymentLogs extends BaseCommand
{
    protected ?string $jsonDataClass = DeploymentLog::class;

    protected $signature = 'deployment:logs
                            {deployment? : The deployment ID}';

    protected $description = 'View the build and deploy logs of a deployment';

    public function handle()
    {
        $this->ensureClient();

        intro('Deployment Logs');

        $deployment = $this->resolvers()->deployment()->from($this->argument('deployment'));

        $log = spin(
            fn () => $this->client->deployments()->logs($deployment->id),
            'Fetching deployment logs...',
        );

        $this->outputJsonIfWanted($log);

        (new DeploymentLogsPrompt($log))->display();
    }
}
