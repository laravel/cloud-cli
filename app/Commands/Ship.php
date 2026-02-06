<?php

namespace App\Commands;

use App\Actions\CreateDatabase;
use App\Actions\CreateDatabaseCluster;
use App\Actions\CreateWebSocketApplication;
use App\Actions\CreateWebSocketCluster;
use App\Concerns\HandlesAvatars;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Concerns\Validates;
use App\Dto\Application;
use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\Environment;
use App\Dto\Region;
use App\Dto\ValidationErrors;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use App\Git;
use Carbon\CarbonInterval;
use Dotenv\Dotenv;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class Ship extends BaseCommand
{
    use HandlesAvatars;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;
    use Validates;

    protected $signature = 'ship';

    protected $description = 'Ship the application to Laravel Cloud';

    protected ?string $appName = null;

    protected ?string $region = null;

    protected Git $git;

    public function handle(Git $git)
    {
        $this->git = $git;

        slideIn('WE MUST *SHIP*');

        intro('Shipping Application To Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $repository = $this->git->remoteRepo();

        $applications = spin(
            fn () => $this->client->applications()->withDefaultIncludes()->list(),
            'Checking for existing application...',
        );

        $existingApps = $applications->collect()->filter(
            fn (Application $app) => $app->repositoryFullName === $repository,
        );

        if ($existingApps->isNotEmpty()) {
            info('Found '.$existingApps->count().' existing '.str('application')->plural($existingApps->count()).' for this repository.');

            $options = $existingApps
                ->mapWithKeys(fn (Application $app) => [$app->id => 'Deploy '.$app->name])
                ->collect()
                ->prepend('Create new application', 'new');

            $selectedApp = select(
                label: 'Application',
                options: $options->toArray(),
            );

            if ($selectedApp !== 'new') {
                $this->call('deploy', [
                    'application' => $selectedApp,
                ], $this->output);

                return;
            }
        }

        $mostUsedRegion = $applications->collect()->pluck('region')->countBy()->sortDesc()->keys()->first();
        $defaultRegion = $mostUsedRegion ?? 'us-east-2';

        $application = $this->loopUntilValid(
            fn ($errors) => $this->createApplication($errors, $defaultRegion, $repository),
        );

        $this->tryToSetAvatar($application);

        success('Application created!');

        $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
        $this->appName = $application->name;
        $this->region = $application->region;
        $environment = $this->client->environments()->include('instances')->get($application->defaultEnvironmentId ?? '');

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

        $this->call('deploy', [
            'application' => $application->id,
        ], $this->output);

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
                    ], $this->output);

                    return;
                }
            }
        }

        outro('Shipped: '.$environment->url);
    }

    protected function tryToSetAvatar(Application $application): void
    {
        $avatars = $this->getAvatarCandidatesFromRepo();

        if ($avatars->isEmpty()) {
            return;
        }

        try {
            $this->client->applications()->update($application->id, [
                'avatar' => $avatars->first(),
            ]);
        } catch (Throwable $e) {
            // All good, this is a nice bonus but not critical
        }
    }

    protected function waitForUrlToBeReady(Environment $environment): bool
    {
        do {
            $response = Http::get($environment->url);
            Sleep::for(CarbonInterval::seconds(2));
        } while (! $response->successful() && ! $response->serverError());

        return $response->successful();
    }

    protected function createApplication(ValidationErrors $errors, string $defaultRegion, string $repository): ?Application
    {
        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Application name',
                default: $value ?? str($this->git->remoteRepo())->afterLast('/')->toString(),
                required: true,
            )),
        );

        $this->addParam(
            'region',
            fn ($resolver) => $resolver->fromInput(function ($value) use ($defaultRegion) {
                $regions = spin(
                    fn () => $this->client->meta()->regions(),
                    'Fetching regions...',
                );

                return select(
                    label: 'Region',
                    options: collect($regions)->mapWithKeys(
                        fn (Region $region) => [
                            $region->value => $region->label,
                        ],
                    ),
                    default: $value ?? $defaultRegion,
                );
            }),
        );

        return dynamicSpinner(
            fn () => $this->client->applications()->create(
                $repository,
                $this->getParam('name'),
                $this->getParam('region'),
            ),
            'Creating application',
        );
    }

    protected function collectOptionsToEnable(Environment $environment): void
    {
        $composer = new Composer(app('files'), getcwd());
        $enableOptions = [
            'scheduler' => 'Laravel Scheduler',
            'hibernation' => 'Hibernation',
            'database_cluster' => 'Database Cluster',
        ];

        $packages = [
            'inertiajs/inertia-laravel' => ['inertia_ssr' => 'Inertia SSR'],
            'laravel/octane' => ['octane' => 'Laravel Octane'],
            'laravel/reverb' => ['websocket_cluster' => 'Websocket Cluster'],
        ];

        foreach ($packages as $package => $options) {
            if ($composer->hasPackage($package)) {
                $enableOptions = array_merge($enableOptions, $options);
            }
        }

        $selectedOptions = multiselect(
            label: 'Enable any of the following features?',
            options: $enableOptions,
        );

        if (count($selectedOptions) === 0) {
            return;
        }

        $instanceParams = [];
        $environmentParams = [];

        $mapping = [
            'scheduler' => 'uses_scheduler',
            'octane' => 'uses_octane',
            'inertia_ssr' => 'uses_inertia_ssr',
            'hibernation' => 'uses_sleep_mode',
        ];

        foreach ($mapping as $option => $param) {
            if (in_array($option, $selectedOptions)) {
                $instanceParams[$param] = true;
            }
        }

        if ($instanceParams['uses_sleep_mode'] ?? false) {
            $hibernateFor = text(
                label: 'Hibernate after',
                default: '5',
                required: true,
                validate: fn ($value) => match (true) {
                    ! is_numeric($value) => 'Must be a number',
                    intval($value) < 1 => 'Must be at least 1',
                    intval($value) > 60 => 'Must be less than 60',
                    default => null,
                },
                hint: 'The number of minutes without HTTP requests received before your application hibernates (1-60)',
            );

            $instanceParams['sleep_timeout'] = $hibernateFor;
        }

        if (in_array('database_cluster', $selectedOptions)) {
            $cluster = $this->getDatabaseCluster();

            if ($cluster) {
                $cluster = $this->client->databaseClusters()->include('schemas')->get($cluster->id);
                $database = $this->getDatabase($cluster);
                $environmentParams['database_schema_id'] = $database->id;
            }
        }

        if (in_array('websocket_cluster', $selectedOptions)) {
            $cluster = $this->getWebsocketCluster();

            if ($cluster) {
                $cluster = $this->client->websocketClusters()->get($cluster->id);
                $websocketApplication = $this->getWebSocketApplication($cluster);

                if ($websocketApplication) {
                    $environmentParams['websocket_application_id'] = $websocketApplication->id;
                }
            }
        }

        if (count($instanceParams) > 0) {
            $this->loopUntilValid(
                fn () => spin(
                    fn () => $this->client->instances()->update($environment->instances[0], $instanceParams),
                    'Updating instance...',
                ),
            );
        }

        if (count($environmentParams) > 0) {
            $this->loopUntilValid(
                function ($errors) use ($environmentParams, $environment) {
                    if ($errors->messageContains('global', 'wait a few seconds')) {
                        info('Waiting a few seconds...');
                        Sleep::for(CarbonInterval::seconds(5));
                    }

                    return spin(fn () => $this->client->environments()->update($environment->id, $environmentParams), 'Updating environment...');
                },
            );
        }
    }

    protected function getWebSocketApplication(WebsocketCluster $cluster): ?WebsocketApplication
    {
        $applicationsPaginator = $this->client->websocketApplications()->list($cluster->id);
        $applications = $applicationsPaginator->collect();

        if ($applications->isEmpty()) {
            return null;
        }

        $options = $applications->collect()->mapWithKeys(fn (WebsocketApplication $application) => [$application->id => $application->name]);
        $options->prepend('Create new websocket application', 'new');

        $application = select(
            label: 'Websocket application',
            options: $options->toArray(),
        );

        if ($application !== 'new') {
            return $applications->firstWhere('id', $application);
        }

        return $this->loopUntilValid(
            fn () => app(CreateWebSocketApplication::class)->run($this->client, $cluster, []),
        );
    }

    protected function getWebsocketCluster(): ?WebsocketCluster
    {
        $clustersPaginator = $this->client->websocketClusters()->list();
        $clusters = $clustersPaginator->collect();

        if ($clusters->isEmpty()) {
            warning('No websocket clusters found.');

            $createWebsocketCluster = confirm('Do you want to create a new websocket cluster?');

            if ($createWebsocketCluster) {
                return $this->loopUntilValid(
                    fn () => app(CreateWebSocketCluster::class)->run($this->client, $this->websocketClusterDefaults()),
                );
            }

            return null;
        }

        $options = $clusters->collect()->mapWithKeys(fn (WebsocketCluster $cluster) => [$cluster->id => $cluster->name]);
        $options->prepend('Create new websocket cluster', 'new');

        $cluster = select(
            label: 'Websocket cluster',
            options: $options->toArray(),
            default: $clusters->first()?->id ?? null,
            required: true,
        );

        if ($cluster !== 'new') {
            return $clusters->firstWhere('id', $cluster);
        }

        return $this->loopUntilValid(
            fn () => app(CreateWebSocketCluster::class)->run($this->client, $this->getWebSocketClusterDefaults()),
        );
    }

    protected function getWebSocketClusterDefaults(): array
    {
        return array_filter([
            'name' => $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : null,
            'region' => $this->region,
        ]);
    }

    protected function getDatabase(DatabaseCluster $database): ?Database
    {
        $options = collect($database->schemas)->mapWithKeys(fn (Database $schema) => [$schema->id => $schema->name]);
        $options->prepend('Create new database', 'new');

        $schema = select(
            label: 'Database',
            options: $options,
            default: $database->schemas[0]?->id ?? null,
            required: true,
        );

        if ($schema !== 'new') {
            return collect($database->schemas)->firstWhere('id', $schema);
        }

        return $this->loopUntilValid(
            fn () => app(CreateDatabase::class)->run($this->client, $database, []),
        );
    }

    protected function getDatabaseCluster(): ?DatabaseCluster
    {
        $databasesPaginator = $this->client->databaseClusters()->include('schemas')->list();
        $databases = $databasesPaginator->collect();

        if ($databases->isEmpty()) {
            warning('No databases found.');

            $createDatabase = confirm('Do you want to create a new database?');

            if ($createDatabase) {
                return $this->loopUntilValid(
                    fn () => app(CreateDatabaseCluster::class)->run($this->client, $this->databaseClusterDefaults()),
                );
            }

            return null;
        }

        $options = $databases->collect()->mapWithKeys(fn (DatabaseCluster $database) => [$database->id => $database->name]);
        $options->prepend('Create new database cluster', 'new');

        $database = select(
            label: 'Database cluster',
            options: $options->toArray(),
            default: $databases->first()?->id ?? null,
            required: true,
        );

        if ($database !== 'new') {
            return $databases->firstWhere('id', $database);
        }

        return $this->loopUntilValid(
            fn () => app(CreateDatabaseCluster::class)->run($this->client, $this->databaseClusterDefaults()),
        );
    }

    protected function databaseClusterDefaults(): array
    {
        return array_filter([
            'name' => $this->appName ? str($this->appName)->snake()->replace('-', '_')->toString() : null,
            'region' => $this->region,
        ]);
    }

    protected function pushCustomEnvironmentVariables(Application $application): void
    {
        $envPath = getcwd().'/.env';

        if (! file_exists($envPath)) {
            return;
        }

        try {
            $variables = Dotenv::parse(file_get_contents($envPath));
        } catch (Throwable $e) {
            return;
        }

        $diff = array_diff(array_keys($variables), config('env.laravel'));

        if (count($diff) === 0) {
            return;
        }

        $varOptions = collect($diff)->mapWithKeys(fn ($key) => [
            $key => $key.$this->dim(str($variables[$key])->limit(5)->prepend(' (')->append(')')),
        ]);

        $varsToAdd = multiselect(
            label: 'Add local environment variables to Cloud environment?',
            options: $varOptions,
        );

        if (count($varsToAdd) === 0) {
            return;
        }

        $varsToAdd = collect($varsToAdd)->mapWithKeys(fn ($key) => [$key => $variables[$key]]);

        dynamicSpinner(
            function () use ($application, $varsToAdd) {
                while (count($application->environmentIds) === 0) {
                    $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
                    Sleep::for(CarbonInterval::seconds(1));
                }

                $this->client->environments()->addVariables($application->environmentIds[0], $varsToAdd->toArray());
            },
            'Adding selected variables to Cloud environment',
        );
    }
}
