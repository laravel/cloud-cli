<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Dto\Deployment;
use App\Dto\Environment;
use App\Git;
use App\Prompts\MonitorDeployments;
use App\Support\Notification;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DeployMonitor extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

    protected $signature = 'deploy:monitor
                            {application? : The application ID or name}
                            {environment? : The name of the environment to deploy}';

    protected $description = 'Monitor application deployments to Laravel Cloud';

    public function handle()
    {
        $this->newLine();
        slideIn('EYES ON THE *SKY*');
        $this->newLine();

        intro('Monitoring Application Deployments');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $repository = app(Git::class)->remoteRepo();

        $applications = spin(
            fn () => $this->client->applications()->include('organization', 'environments', 'defaultEnvironment')->list(),
            'Checking for existing application...',
        );

        $existingApps = collect($applications->items())->filter(
            fn ($app) => $app->repositoryFullName === $repository,
        );

        if ($existingApps->isEmpty()) {
            warning('No existing Cloud application found for this repository.');

            $shouldShip = confirm('Do you want to ship this application to Laravel Cloud?');

            if ($shouldShip) {
                Artisan::call('ship', [], $this->output);

                return;
            }

            error('Monitoring cancelled.');

            exit(1);
        }

        $app = $this->getCloudApplication($existingApps);

        $environments = spin(
            fn () => $this->client->environments()->list($app->id),
            'Checking for existing environments...',
        );

        $environment = $this->getEnvironment(collect($environments->items()));

        (new MonitorDeployments(
            fn ($deploymentId = null) => $this->getCurrentDeployment($environment, $deploymentId),
            $environment,
        ))->display();
    }

    protected function getCurrentDeployment(Environment $environment, ?string $deploymentId = null): ?Deployment
    {
        if ($deploymentId) {
            $deployment = $this->client->deployments()->get($deploymentId);

            if ($deployment->isFinished()) {
                Notification::send(
                    'Deployment Completed',
                    'Deployment completed in '.$deployment->totalTime()->format('%I:%S'),
                );
            }

            return $deployment;
        }

        $deployments = collect($this->client->deployments()->list($environment->id)->items());

        if ($deployments->isEmpty()) {
            return null;
        }

        $deployment = $deployments->first()?->isFinished() ? null : $deployments->first();

        return $deployment;
    }
}
