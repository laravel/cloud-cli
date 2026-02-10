<?php

namespace App\Commands;

use App\Client\Requests\CreateApplicationRequestData;
use App\Client\Requests\UpdateApplicationRequestData;
use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Client\Requests\UpdateInstanceRequestData;
use App\Concerns\CreatesDatabase;
use App\Concerns\CreatesDatabaseCluster;
use App\Concerns\CreatesWebSocketApplication;
use App\Concerns\CreatesWebSocketCluster;
use App\Concerns\HandlesAvatars;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Dto\Application;
use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\Environment;
use App\Dto\Region;
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
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class Ship extends BaseCommand
{
    use CreatesDatabase;
    use CreatesDatabaseCluster;
    use CreatesWebSocketApplication;
    use CreatesWebSocketCluster;
    use HandlesAvatars;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;

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
                $this->call(Deploy::class, [
                    'application' => $selectedApp,
                ]);

                return;
            }
        }

        $mostUsedRegion = $applications->collect()->pluck('region')->countBy()->sortDesc()->keys()->first();
        $defaultRegion = $mostUsedRegion ?? 'us-east-2';

        $application = $this->loopUntilValid(
            fn ($errors) => $this->createApplication($defaultRegion, $repository),
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

        $this->call(Deploy::class, [
            'application' => $application->id,
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

    protected function tryToSetAvatar(Application $application): void
    {
        $avatars = $this->getAvatarCandidatesFromRepo();

        if ($avatars->isEmpty()) {
            return;
        }

        try {
            $path = $avatars->first();
            $this->client->applications()->update(new UpdateApplicationRequestData(
                applicationId: $application->id,
                avatar: [file_get_contents($path), pathinfo($path, PATHINFO_EXTENSION)],
            ));
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

    protected function createApplication(string $defaultRegion, string $repository): ?Application
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Application name',
                default: $value ?? str($this->git->remoteRepo())->afterLast('/')->toString(),
                required: true,
            )),
        );

        $this->form()->prompt(
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
            fn () => $this->client->applications()->create(new CreateApplicationRequestData(
                repository: $repository,
                name: $this->form()->get('name'),
                region: $this->form()->get('region'),
            )),
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
            $hibernateFor = number(
                label: 'Hibernate after',
                default: '5',
                required: true,
                hint: 'The number of minutes without HTTP requests received before your application hibernates (1-60)',
                min: 1,
                max: 60,
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
                    fn () => $this->client->instances()->update(new UpdateInstanceRequestData(
                        instanceId: $environment->instances[0],
                        usesScheduler: $instanceParams['uses_scheduler'] ?? null,
                        usesOctane: $instanceParams['uses_octane'] ?? null,
                        usesInertiaSsr: $instanceParams['uses_inertia_ssr'] ?? null,
                        usesSleepMode: $instanceParams['uses_sleep_mode'] ?? null,
                        sleepTimeout: isset($instanceParams['sleep_timeout']) ? (int) $instanceParams['sleep_timeout'] : null,
                    )),
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

                    return spin(fn () => $this->client->environments()->update(new UpdateEnvironmentRequestData(
                        environmentId: $environment->id,
                        databaseSchemaId: $environmentParams['database_schema_id'] ?? null,
                        websocketApplicationId: $environmentParams['websocket_application_id'] ?? null,
                    )), 'Updating environment...');
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
            fn () => $this->createWebSocketApplication($cluster, []),
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
                    fn () => $this->createWebSocketCluster(),
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
            fn () => $this->createWebSocketCluster($this->getWebSocketClusterDefaults()),
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
            fn () => $this->createDatabase($database),
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
                    fn () => $this->createDatabaseCluster($this->databaseClusterDefaults()),
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
            fn () => $this->createDatabaseCluster($this->databaseClusterDefaults()),
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
