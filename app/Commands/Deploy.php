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
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class Deploy extends Command
{
    use Colors;
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

    protected $signature = 'deploy '
        .'{application? : The application ID or name} '
        .'{environment? : The name of the environment to deploy} '
        .'{--open : Open the site in the browser after a successful deployment}';

    protected $description = 'Deploy the application to Laravel Cloud';

    public function handle()
    {
        $this->newLine();
        slideIn('TO THE *CLOUD*');
        $this->newLine();

        intro('Deploying application to Laravel Cloud');

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

            error('Deployment cancelled.');

            exit(1);
        }

        $app = $this->getCloudApplication($existingApps);

        $environments = spin(
            fn () => $this->client->listEnvironments($app->id),
            'Checking for existing environments...'
        );

        $environment = $this->getEnvironment(collect($environments->data));

        $deployment = $this->client->initiateDeployment($environment->id);

        dynamicSpinner(
            fn (callable $updateMessage) => $this->updateDeploymentStatus($deployment, $updateMessage),
            $this->getDeploymentMessage($deployment),
        );

        $deployment = $this->client->getDeployment($deployment->id);

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

        if ($environment->url) {
            if ($this->option('open') || confirm('Open site in browser?')) {
                Process::run('open '.$environment->url);
            }

            outro($environment->url);
        } else {
            outro('Deployment completed in <comment>'.$deployment->totalTime()->format('%I:%S').'</comment>');
        }
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
                $deploymentStatus = $this->client->getDeployment($deployment->id);
            }

            $newMessage = $this->getDeploymentMessage($deploymentStatus);

            $updateMessage($newMessage, $lastMessage !== $deploymentStatus->status->label());

            $lastMessage = $deploymentStatus->status->label();

            Sleep::for(CarbonInterval::milliseconds($updateInterval));
            $count++;
            $checkApi = $count % $checkInterval === 0;
        } while ($deploymentStatus->isInProgress());
    }

    protected function getDeploymentMessage(Deployment $deployment): string
    {
        return $this->dim($deployment->timeElapsed()->format('%I:%S')).' '.$deployment->status->label();
    }
}
