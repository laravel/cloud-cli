<?php

namespace App\Commands;

use App\Commands\Concerns\ConfiguresEnvironmentFeatures;
use App\Commands\Concerns\DetectsOrCreatesApplication;
use App\Commands\Concerns\ImportsEnvironmentVariables;
use App\Commands\Concerns\MonitorsDeployedSite;
use App\Commands\Concerns\ProvisionsDatabases;
use App\Commands\Concerns\ProvisionsWebsockets;
use App\Concerns\CreatesDatabase;
use App\Concerns\CreatesDatabaseCluster;
use App\Concerns\CreatesWebSocketApplication;
use App\Concerns\CreatesWebSocketCluster;
use App\Concerns\HandlesAvatars;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Git;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class Ship extends BaseCommand
{
    use ConfiguresEnvironmentFeatures;
    use CreatesDatabase;
    use CreatesDatabaseCluster;
    use CreatesWebSocketApplication;
    use CreatesWebSocketCluster;
    use DetectsOrCreatesApplication;
    use HandlesAvatars;
    use ImportsEnvironmentVariables;
    use MonitorsDeployedSite;
    use ProvisionsDatabases;
    use ProvisionsWebsockets;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

    protected $signature = 'ship
                            {--database= : Database type or alias (postgres, postgres18, postgres17, mysql, or a full type like neon_serverless_postgres_18). Default: postgres18}
                            {--database-preset= : Preset tier for the database (dev, prod, scale — case-insensitive). Default: dev}
                            {--name= : Application name (non-interactive). Default: derived from repository}
                            {--region= : Region (non-interactive). Default: most-used or us-east-2}
                            {--json : Output application_id, environment_id, and url as JSON}';

    protected $description = 'Ship the application to Laravel Cloud';

    protected ?string $appName = null;

    protected ?string $region = null;

    protected Git $git;

    public function handle(Git $git)
    {
        $this->git = $git;

        slideIn('WE MUST *SHIP*');

        intro('Shipping Application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $result = $this->detectOrCreateApplication($git);

        if ($result['existing']) {
            $this->call(Deploy::class, [
                'application' => $result['application']->id,
            ]);

            return;
        }

        $application = $result['application'];

        $this->tryToSetAvatar($application);

        success('Application created!');

        $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
        $this->appName = $application->name;
        $this->region = $application->region;
        $environment = $this->client->environments()->include('instances')->get($application->defaultEnvironmentId ?? '');

        if (! $this->isInteractive()) {
            $this->applyOpinionatedOptions($environment);
        } else {
            $this->loopUntilValid(
                fn () => $this->pushCustomEnvironmentVariables($application),
            );

            $this->loopUntilValid(
                fn () => $this->collectOptionsToEnable($environment),
            );

            success($application->url());

            if (! confirm('Do you want to deploy the application?')) {
                return;
            }

            if (confirm('Do you want to edit the build and deploy commands before deploying?')) {
                $this->updateCommands($environment);
            }
        }

        $this->call(Deploy::class, array_filter([
            'application' => $application->id,
            '--no-interaction' => ! $this->isInteractive(),
        ]));

        $environment = $this->client->environments()->include('instances')->get($application->defaultEnvironmentId ?? '');

        $this->outputJsonIfWanted([
            'application_id' => $application->id,
            'environment_id' => $environment->id,
            'url' => $environment->url,
        ]);

        if (confirm('Open site in browser?')) {
            $isReady = spin(
                fn () => $this->waitForUrlToBeReady($environment),
                'Waiting for site to be ready...',
            );

            if ($isReady) {
                Process::run('open '.$environment->url);
            } else {
                warning('It looks like there is an error in your deployed site');

                info($environment->url);

                if (confirm('Do you want to check the logs?')) {
                    $this->call(EnvironmentLogs::class, [
                        'application' => $application->id,
                        'environment' => $environment->id,
                    ]);

                    return;
                }
            }
        }

        outro('Shipped: '.$environment->url);
    }
}
