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
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class DeployMonitor extends Command
{
    use Colors;
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

    protected $signature = 'deploy:monitor '
        .'{application? : The application ID or name} '
        .'{environment? : The name of the environment to deploy}';

    protected $description = 'Monitor application deployments to Laravel Cloud';

    public function handle()
    {
        $this->newLine();
        slideIn('EYES ON THE *SKY*');
        $this->newLine();

        intro('Monitoring application deployments');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $repository = app(Git::class)->remoteRepo();

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Checking for existing application...'
        );

        $existingApps = collect($applications->data ?? [])->filter(
            fn ($app) => $app->repositoryFullName === $repository
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
            fn () => $this->client->listEnvironments($app->id),
            'Checking for existing environments...'
        );

        $environment = $this->getEnvironment(collect($environments->data));

        (new MonitorDeployments(
            fn ($deploymentId = null) => $this->getCurrentDeployment($environment, $deploymentId),
            $environment,
        ))->display();
    }

    protected function getCurrentDeployment(Environment $environment, ?string $deploymentId = null): ?Deployment
    {
        if ($deploymentId) {
            $deployment = $this->client->getDeployment($deploymentId);

            if ($deployment->isFinished()) {
                Notification::send(
                    'Deployment Completed',
                    'Deployment completed in '.$deployment->totalTime()->format('%I:%S'),
                );
            }

            return $deployment;
        }

        $deployments = $this->client->listDeployments($environment->id);

        if (count($deployments->data) === 0) {
            return null;
        }

        $deployment = ($deployments->data[0]->isFinished()) ? null : $deployments->data[0];

        return $deployment;
    }
}
