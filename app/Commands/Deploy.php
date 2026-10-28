<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\InitiateDeploymentRequestData;
use App\Concerns\ReadsLocalEnvironmentVariables;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Concerns\WatchesDeployments;
use App\Dto\Environment;
use App\Exceptions\CommandExitException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class Deploy extends BaseCommand
{
    use ReadsLocalEnvironmentVariables;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;
    use WatchesDeployments;

    protected $signature = 'deploy
                            {application? : The application ID or name}
                            {environment? : The name of the environment to deploy}
                            {--open : Open the site in the browser after a successful deployment}
                            {--no-wait : Initiate deployment and return immediately}
                            {--push-env : Choose variables from the local .env to set before deploying}
                            {--push-env-keys= : Comma-separated keys from the local .env to set before deploying}';

    protected $description = 'Deploy an application to Laravel Cloud';

    public function handle()
    {
        slideIn('TO THE *CLOUD*');

        intro('Deploying Application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $app = $this->resolvers()->application()->nullable()->from($this->argument('application'));

        if (! $app) {
            if (! $this->isInteractive()) {
                $this->writeJsonIfWanted([
                    'status' => 'redirect',
                    'message' => 'No application found for this repository. Running `cloud ship` to create one. If this fails, run `cloud ship` directly.',
                    'command' => 'ship',
                ]);
            } else {
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

        if ($this->option('push-env') || $this->option('push-env-keys') !== null) {
            $this->pushEnvironmentVariables($environment);
        }

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

        $deployment = $this->watchDeployment($deployment);

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

    protected function pushEnvironmentVariables(Environment $environment): void
    {
        $named = collect(explode(',', (string) $this->option('push-env-keys')))
            ->map(fn (string $key) => trim($key))
            ->filter()
            ->values();

        // A key named on the command line is pushed even if it is one of Laravel's own.
        $local = $this->localEnvironmentVariables((string) getcwd(), includeLaravelKeys: $named->isNotEmpty());

        if ($named->isNotEmpty()) {
            $missing = $named->diff(array_keys($local));

            if ($missing->isNotEmpty()) {
                $this->failAndExit('Not found in the local .env file: '.$missing->implode(', '));
            }

            $keys = $named->all();
        } elseif (! $this->isInteractive()) {
            $this->failAndExit('Name the variables to push, e.g. --push-env-keys=KEY_ONE,KEY_TWO');
        } else {
            $keys = $this->chooseVariablesToPush($environment, $local);
        }

        if ($keys === []) {
            return;
        }

        spin(
            fn () => $this->client->environments()->addVariables(
                new AddEnvironmentVariablesRequestData(
                    environmentId: $environment->id,
                    variables: collect($keys)->map(fn (string $key) => ['key' => $key, 'value' => $local[$key]])->all(),
                    method: 'set',
                ),
            ),
            'Setting environment variables...',
        );

        success('Set '.implode(', ', $keys));
    }

    /**
     * @param  array<string, string|null>  $local
     * @return list<string>
     */
    protected function chooseVariablesToPush(Environment $environment, array $local): array
    {
        $remote = collect($environment->environmentVariables)->pluck('value', 'key');

        $changed = collect($local)
            ->filter(fn ($value, string $key) => ! $remote->has($key) || $remote->get($key) !== $value)
            ->keys();

        if ($changed->isEmpty()) {
            info('No new or changed variables in the local .env file.');

            return [];
        }

        return multiselect(
            label: 'Which local variables should be set on '.$environment->name.'?',
            options: $changed->all(),
            hint: 'New or changed compared to Cloud.',
        );
    }
}
