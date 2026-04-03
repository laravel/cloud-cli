<?php

namespace App\Commands;

use App\Client\Requests\InitiateDeploymentRequestData;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Dto\Deployment;
use App\Exceptions\CommandExitException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class Deploy extends BaseCommand
{
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

    protected $signature = 'deploy
                            {application? : The application ID or name}
                            {environment? : The name of the environment to deploy}
                            {--open : Open the site in the browser after a successful deployment}
                            {--no-wait : Initiate deployment and return immediately}';

    protected $description = 'Deploy an application to Laravel Cloud';

    public function handle()
    {
        slideIn('TO THE *CLOUD*');

        intro('Deploying Application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $app = $this->resolvers()->application()->nullable()->from($this->argument('application'));

        if (! $app) {
            if ($this->isInteractive()) {
                warning('No existing Cloud application found for this repository.');

                if (! confirm('Do you want to ship this application to Laravel Cloud?')) {
                    error('Cancelled');

                    return self::FAILURE;
                }
            }

            Artisan::call('ship', [], $this->output);

            return;
        }

        $environment = $this->resolvers()->environment()->withApplication($app)->from($this->argument('environment'));

        $deployment = $this->client->deployments()->initiate(
            new InitiateDeploymentRequestData($environment->id),
        );

        $this->writeJsonIfWanted([
            'deployment_id' => $deployment->id,
            'status' => 'initiated',
            'timestamp' => CarbonImmutable::now()->timestamp,
        ]);

        if ($this->option('no-wait')) {
            $this->outputJsonIfWanted([
                'deployment_id' => $deployment->id,
                'status' => $deployment->status->value,
            ]);

            success('Deployment initiated: '.$deployment->id);

            return self::SUCCESS;
        }

        dynamicSpinner(
            fn (callable $updateMessage) => $this->updateDeploymentStatus($deployment, $updateMessage),
            $this->getDeploymentMessage($deployment),
        );

        $deployment = $this->client->deployments()->get($deployment->id);

        if ($deployment->failed()) {
            $this->writeJsonIfWanted([
                'deployment_id' => $deployment->id,
                'status' => 'failed',
                'failure_reason' => $deployment->failureReason,
                'hint' => 'Check build/deploy commands with environment:get, update with environment:update',
            ]);

            error('Deployment failed: '.$deployment->failureReason);

            if ($this->isInteractive()) {
                if (confirm('Do you want to edit the build and deploy commands and try again?')) {
                    $this->updateCommands($environment);

                    if (confirm('Re-deploy?')) {
                        Artisan::call('deploy', [
                            'application' => $app->id,
                            'environment' => $environment->name,
                            '--open' => $this->option('open'),
                        ], $this->output);

                        throw new CommandExitException(0);
                    }
                }
            }

            throw new CommandExitException(1);
        }

        success('Deployment completed in <comment>'.$deployment->totalTime()->format('%I:%S').'</comment>');

        if ($this->option('open')) {
            openUrl($environment->url);
        }

        $this->outputJsonIfWanted([
            'status' => $deployment->status->value,
            'message' => $deployment->status->monitorLabel(),
            'timestamp' => CarbonImmutable::now()->timestamp,
            'duration' => $deployment->totalTime()->format('%I:%S'),
            'url' => $environment->url,
        ]);

        outro($environment->url);
    }

    protected function updateDeploymentStatus(Deployment $deployment, callable $updateMessage): void
    {
        $checkApi = true;
        $count = 0;
        $checkInterval = 3;
        $updateInterval = 900;
        $lastMessage = '';
        $deploymentStatus = $this->client->deployments()->get($deployment->id);

        do {
            if ($checkApi) {
                $deploymentStatus = $this->client->deployments()->get($deployment->id);
            }

            $newMessage = $this->getDeploymentMessage($deploymentStatus);

            if (! $this->isInteractive() && $lastMessage !== $deploymentStatus->status->monitorLabel()) {
                $this->line(json_encode([
                    'status' => $deploymentStatus->status->value,
                    'message' => $deploymentStatus->status->monitorLabel(),
                    'timestamp' => CarbonImmutable::now()->timestamp,
                ]));
            }

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
