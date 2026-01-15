<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\ConfigRepository;
use App\Dto\Deployment;
use App\Git;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Exception;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

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

    protected $signature = 'deploy {application? : The ID of the application to deploy} {environment? : The name of the environment to deploy}';

    protected $description = 'Deploy the application to Laravel Cloud';

    public function handle(ConfigRepository $config, Git $git)
    {
        intro('Deploying application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureGitHubRepo($git);
        $this->ensureCloudApplication($git);
    }

    protected function ensureCloudApplication(Git $git): void
    {
        $repository = $git->remoteRepo();

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Checking for existing application...'
        );

        $existingApps = collect($applications->data ?? [])->filter(
            fn ($app) => $app->repositoryFullName === $repository
        );

        if ($existingApps->isEmpty()) {
            warning('No existing Cloud application found for this repository.');

            exit(1);
        }

        $app = $this->getApplication($existingApps);

        $environments = spin(
            fn () => $this->client->listEnvironments($app->id),
            'Checking for existing environments...'
        );

        $environment = $this->getEnvironment(collect($environments->data));

        $deployment = $this->client->initiateDeployment($environment->id);

        dynamicSpinner(
            $this->getDeploymentMessage($deployment),
            fn (callable $updateMessage) => $this->updateDeploymentStatus($deployment, $updateMessage),
        );

        $deployment = $this->client->getDeployment($deployment->id);

        outro('Deployment completed in <info>'.$deployment->totalTime()->format('%I:%S').'</info>');
    }

    protected function updateDeploymentStatus(Deployment $deployment, callable $updateMessage): void
    {
        $checkApi = true;
        $count = 0;
        $checkInterval = 3;
        $updateInterval = 900;
        $dotFrames = ['', '.', '..', '...', '...'];
        $lastMessage = '';
        $dotFrameIndex = 0;

        do {
            if ($checkApi) {
                $deploymentStatus = $this->client->getDeployment($deployment->id);
            }

            $newMessage = $this->getDeploymentMessage($deploymentStatus);

            if ($lastMessage !== $deployment->status->label()) {
                $dotFrameIndex = 0;
            }

            $lastMessage = $deployment->status->label();

            if (! str_ends_with($lastMessage, '!')) {
                $newMessage .= $this->dim($dotFrames[$dotFrameIndex % count($dotFrames)]);
            }

            $updateMessage($newMessage);

            Sleep::for(CarbonInterval::milliseconds($updateInterval));
            $count++;
            $dotFrameIndex++;
            $checkApi = $count % $checkInterval === 0;
        } while (! $deploymentStatus->isCompleted());
    }

    protected function getDeploymentMessage(Deployment $deployment): string
    {
        $timeElapsed = $deployment->startedAt?->diffInSeconds(CarbonImmutable::now());

        return sprintf(
            $this->dim('%s:%s').' <info>%s</info>',
            str_pad(floor($timeElapsed / 60), 2, '0', STR_PAD_LEFT),
            str_pad($timeElapsed % 60, 2, '0', STR_PAD_LEFT),
            $deployment->status->label(),
        );
    }

    protected function ensureGitHubRepo(Git $git): void
    {
        if ($git->hasGitHubRemote()) {
            return;
        }

        throw new Exception('GitHub repository not found');
    }
}
