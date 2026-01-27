<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Dto\Deployment;
use App\Git;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class Deploy extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

    protected $signature = 'deploy
                            {application? : The application ID or name}
                            {environment? : The name of the environment to deploy}
                            {--open : Open the site in the browser after a successful deployment}';

    protected $description = 'Deploy the application to Laravel Cloud';

    public function handle()
    {
        $this->newLine();
        slideIn('TO THE *CLOUD*');
        $this->newLine();

        intro('Deploying Application To Laravel Cloud');

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

            error('Deployment cancelled.');

            exit(1);
        }

        $app = $this->getCloudApplication($existingApps);

        $environments = spin(
            fn () => $this->client->environments()->list($app->id),
            'Checking for existing environments...',
        );

        $environment = $this->getEnvironment(collect($environments->items()));

        $deployment = $this->client->deployments()->initiate($environment->id);

        dynamicSpinner(
            fn (callable $updateMessage) => $this->updateDeploymentStatus($deployment, $updateMessage),
            $this->getDeploymentMessage($deployment),
        );

        $deployment = $this->client->deployments()->get($deployment->id);

        if ($deployment->failed()) {
            error('Deployment failed: '.$deployment->failureReason);

            if (confirm('Do you want to edit the build and deploy commands and try again?')) {
                $this->updateCommands($environment);

                if (confirm('Re-deploy?')) {
                    Artisan::call('deploy', [
                        'application' => $app->id,
                        'environment' => $environment->name,
                        '--open' => $this->option('open'),
                    ], $this->output);

                    exit(0);
                }
            }

            exit(1);
        }

        success('Deployment completed in <comment>'.$deployment->totalTime()->format('%I:%S').'</comment>');

        if ($this->option('open')) {
            Process::run('open '.$environment->url);
        }

        outro($environment->url);
    }

    protected function updateDeploymentStatus(Deployment $deployment, callable $updateMessage): void
    {
        $checkApi = true;
        $count = 0;
        $checkInterval = 3;
        $updateInterval = 900;
        $lastMessage = '';

        do {
            if ($checkApi) {
                $deploymentStatus = $this->client->deployments()->get($deployment->id);
            }

            $newMessage = $this->getDeploymentMessage($deploymentStatus);

            $updateMessage($newMessage, $lastMessage !== $deploymentStatus->status->monitorLabel());

            $lastMessage = $deploymentStatus->status->monitorLabel();

            Sleep::for(CarbonInterval::milliseconds($updateInterval));
            $count++;
            $checkApi = $count % $checkInterval === 0;
        } while ($deploymentStatus->isInProgress());
    }

    protected function getDeploymentMessage(Deployment $deployment): string
    {
        return $this->dim($deployment->timeElapsed()->format('%I:%S')).' '.$deployment->status->monitorLabel();
    }
}
