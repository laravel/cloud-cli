<?php

namespace App\Commands;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\CreateApplicationRequestData;
use App\Client\Requests\UpdateApplicationAvatarRequestData;
use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Client\Requests\UpdateInstanceRequestData;
use App\Concerns\CreatesDatabase;
use App\Concerns\CreatesDatabaseCluster;
use App\Concerns\CreatesWebSocketApplication;
use App\Concerns\CreatesWebSocketCluster;
use App\Concerns\HandlesAvatars;
use App\Concerns\ProvisionsDefaultResources;
use App\Concerns\ProvisionsQueueWorker;
use App\Concerns\ReadsLocalEnvironmentVariables;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\ResolvesSourceProvider;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Dto\Application;
use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\Environment;
use App\Dto\Region;
use App\Dto\ValidationErrors;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;
use App\Exceptions\CommandExitException;
use App\Git;
use Carbon\CarbonInterval;
use Illuminate\Support\Composer;
use Illuminate\Support\Sleep;
use Saloon\Exceptions\Request\RequestException;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\task;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class Ship extends BaseCommand
{
    use CreatesDatabase;
    use CreatesDatabaseCluster;
    use CreatesWebSocketApplication;
    use CreatesWebSocketCluster;
    use HandlesAvatars;
    use ProvisionsDefaultResources;
    use ProvisionsQueueWorker;
    use ReadsLocalEnvironmentVariables;
    use RequiresRemoteGitRepo;
    use ResolvesSourceProvider;
    use UpdatesBuildDeployCommands;

    protected $signature = 'ship
                            {--database= : Database type or alias (postgres, postgres<version>, mysql, or a type from `cloud database:types`). Unversioned aliases use the newest available version. Default: mysql, or postgres where MySQL is not offered}
                            {--database-preset= : Preset for the database, as listed by the API for the type and region (e.g. dev, prod, scale — case-insensitive). Default: dev, or the first preset offered}
                            {--name= : Application name (non-interactive). Default: derived from repository}
                            {--region= : Region (non-interactive). Default: most-used or us-east-2}
                            {--root-directory= : Repository subdirectory containing the app (monorepos)}
                            {--source-provider= : Source provider (github, gitlab, gitlab_self_hosted, bitbucket). Default: detected from the origin remote}
                            {--build-command= : Build command to run on deploy}
                            {--cluster= : Dedicated cluster ID to deploy to (see dedicated-cluster:list)}
                            {--queue= : Queue worker to create (worker, managed, horizon). Default: none when non-interactive}
';

    protected $description = 'Ship a new application to Laravel Cloud';

    protected Git $git;

    public function handle(Git $git)
    {
        $this->git = $git;

        slideIn('WE MUST *SHIP*');

        intro('Shipping Application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();
        $this->resolveRequestedQueueType();

        $repository = $this->git->remoteRepo();

        $applications = spin(
            fn () => $this->client->applications()->withDefaultIncludes()->list(),
            'Checking for existing application...',
        );

        $rootDirectory = $this->rootDirectoryOption();

        // A repository can hold an application per root directory, so only apps rooted at the
        // same directory are duplicates of the one we are about to create.
        $existingApps = $applications->collect()->filter(
            fn (Application $app) => $app->repositoryFullName === $repository
                && $this->normalizeRootDirectory($app->rootDirectory) === $rootDirectory,
        );

        $location = $rootDirectory === null ? 'this repository' : 'this repository with root directory `'.$rootDirectory.'`';

        if ($existingApps->isNotEmpty()) {
            if (! $this->isInteractive()) {
                $this->outputErrorOrThrow(
                    'An application already exists for '.$location.'. Use deploy <application-id> to deploy. Existing: '.$existingApps->pluck('id')->join(', '),
                );
            }

            info('Found '.$existingApps->count().' existing '.str('application')->plural($existingApps->count()).' for '.$location.'.');

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

        $application = $this->isInteractive()
            ? $this->loopUntilValid(fn () => $this->createApplication($defaultRegion, $repository))
            : $this->createApplicationNonInteractively($repository, $defaultRegion);

        $this->tryToSetAvatar($application);

        success('Application created!');

        $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
        $this->appName = $application->name;
        $this->region = $application->region;
        $environment = $this->client->environments()->include('instances')->get($application->defaultEnvironmentId ?? '');

        $queueType = null;

        if (! $this->isInteractive()) {
            $queueType = $this->requestedQueueType;
            $this->applyOpinionatedOptions($environment, $queueType);
        } else {
            $this->loopUntilValid(
                fn () => $this->pushCustomEnvironmentVariables($application),
            );

            $queueType = $this->loopUntilValid(
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

        $this->outputJsonIfWanted(array_filter([
            'application_id' => $application->id,
            'environment_id' => $environment->id,
            'url' => $environment->url,
            'queue' => $queueType,
            'suggestions' => $queueType === null && $this->appDispatchesQueuedJobs()
                ? ['The app has queued jobs but no queue worker. Create one with `cloud background-process:create '.$environment->instances[0].' --type=worker` or `cloud managed-queue:create '.$environment->id.'`.']
                : null,
        ], fn ($value) => $value !== null));

        if (confirm('Open site in browser?')) {
            $isReady = spin(
                fn () => $this->waitForUrlToBeReady($environment),
                'Waiting for site to be ready...',
            );

            if ($isReady) {
                openUrl($environment->url);
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
            $this->client->applications()->updateAvatar(new UpdateApplicationAvatarRequestData(
                applicationId: $application->id,
                avatar: $this->getAvatarFromPath($path),
            ));
        } catch (Throwable $e) {
            // All good, this is a nice bonus but not critical
        }
    }

    protected function createApplicationNonInteractively(string $repository, string $defaultRegion): Application
    {
        $name = $this->option('name') ?? str($repository)->afterLast('/')->toString();
        $region = $this->option('region') ?? $defaultRegion;

        $data = new CreateApplicationRequestData(
            repository: $repository,
            name: $name,
            region: $region,
            rootDirectory: $this->rootDirectoryOption(),
            sourceProvider: $this->resolveSourceProvider(),
            buildCommand: $this->option('build-command') ?: null,
            clusterId: $this->option('cluster') ?: null,
        );

        try {
            return $this->client->applications()->create($data);
        } catch (RequestException $e) {
            if ($e->getResponse()->status() === 422) {
                $errors = $e->getResponse()->json('errors', []);

                $validationErrors = new ValidationErrors;

                foreach ($errors as $field => $msgs) {
                    $validationErrors->add($field, implode(', ', $msgs));
                }

                $values = collect($errors)
                    ->keys()
                    ->mapWithKeys(fn ($field) => [$field => $data->toRequestData()[$field] ?? null])
                    ->filter()
                    ->all();

                fwrite(STDERR, $validationErrors->toJson($values).PHP_EOL);

                throw new CommandExitException(self::FAILURE);
            }

            throw $e;
        }
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
                    )->toArray(),
                    default: $value ?? $defaultRegion,
                );
            }),
        );

        return task(
            label: 'Creating application',
            callback: fn () => $this->client->applications()->create(
                new CreateApplicationRequestData(
                    repository: $repository,
                    name: $this->form()->get('name'),
                    region: $this->form()->get('region'),
                    rootDirectory: $this->rootDirectoryOption(),
                    sourceProvider: $this->resolveSourceProvider(),
                    buildCommand: $this->option('build-command') ?: null,
                    clusterId: $this->option('cluster') ?: null,
                ),
            ),
        );
    }

    protected function rootDirectoryOption(): ?string
    {
        return $this->normalizeRootDirectory($this->option('root-directory'));
    }

    /**
     * The API stores the directory without a trailing slash, which is what shell completion gives you.
     */
    protected function normalizeRootDirectory(?string $rootDirectory): ?string
    {
        $rootDirectory = rtrim((string) $rootDirectory, '/');

        return $rootDirectory === '' ? null : $rootDirectory;
    }

    /**
     * Where the application actually lives on disk.
     *
     * Shipping a monorepo means the command can be run from the repository root, so the
     * project this command inspects is not necessarily the directory it was invoked from.
     */
    protected function projectPath(): string
    {
        $rootDirectory = $this->rootDirectoryOption();

        if ($rootDirectory === null) {
            return (string) getcwd();
        }

        $repositoryRoot = $this->git->getRoot() ?? (string) getcwd();

        return $repositoryRoot.'/'.$rootDirectory;
    }

    protected function avatarSearchRoot(): ?string
    {
        return $this->projectPath();
    }

    /**
     * Package detection is a convenience, so a project we cannot read should not stop the ship.
     */
    protected function composer(): ?Composer
    {
        $path = $this->projectPath();

        if (! file_exists($path.'/composer.json')) {
            return null;
        }

        return new Composer(app('files'), $path);
    }

    protected function collectOptionsToEnable(Environment $environment): ?string
    {
        $composer = $this->composer();

        $enableOptions = [
            'scheduler' => 'Laravel Scheduler',
            'scale_to_zero' => 'Scale to Zero',
            'database_cluster' => 'Database Cluster',
            'queue' => 'Queue Worker',
        ];

        $packages = [
            'inertiajs/inertia-laravel' => ['inertia_ssr' => 'Inertia SSR'],
            'laravel/octane' => ['octane' => 'Laravel Octane'],
            'laravel/reverb' => ['websocket_cluster' => 'Websocket Cluster'],
        ];

        foreach ($packages as $package => $options) {
            if ($composer?->hasPackage($package)) {
                $enableOptions = array_merge($enableOptions, $options);
            }
        }

        $selectedOptions = multiselect(
            label: 'Enable any of the following features?',
            options: $enableOptions,
            default: ['scale_to_zero', ...($this->requestedQueueType !== null || $this->appDispatchesQueuedJobs() ? ['queue'] : [])],
        );

        $queueType = in_array('queue', $selectedOptions) ? $this->promptForQueueType() : null;

        if ($queueType !== 'managed' && $queueType !== null && in_array('scale_to_zero', $selectedOptions)) {
            warning('Queue workers on the app instance stop while it is scaled to zero, so jobs wait for the next web request.');
        }

        $instanceParams = [];
        $environmentParams = [];

        $mapping = [
            'scheduler' => 'uses_scheduler',
            'octane' => 'uses_octane',
            'inertia_ssr' => 'uses_inertia_ssr',
        ];

        foreach ($mapping as $option => $param) {
            if (in_array($option, $selectedOptions)) {
                $instanceParams[$param] = true;
            }
        }

        if (in_array('scale_to_zero', $selectedOptions)) {
            $scaleToZeroAfter = number(
                label: 'Scale to zero after',
                default: '5',
                required: true,
                hint: 'The number of minutes without HTTP requests received before your application scales to zero (1-60)',
                min: 1,
                max: 60,
            );

            $instanceParams['hibernation_timeout'] = (int) $scaleToZeroAfter;
        } else {
            // Cloud turns scale to zero on for new environments, so leaving the option unticked has to switch it off.
            $instanceParams['disable_hibernation'] = true;
        }

        if (in_array('database_cluster', $selectedOptions)) {
            $cluster = $this->getDatabaseCluster();

            if ($cluster) {
                $cluster = $this->client->databaseClusters()->include('databases')->get($cluster->id);
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

        $this->loopUntilValid(
            fn () => spin(
                fn () => $this->client->instances()->update(new UpdateInstanceRequestData(
                    instanceId: $environment->instances[0],
                    usesScheduler: $instanceParams['uses_scheduler'] ?? null,
                    usesOctane: $instanceParams['uses_octane'] ?? null,
                    usesInertiaSsr: $instanceParams['uses_inertia_ssr'] ?? null,
                    hibernationTimeout: $instanceParams['hibernation_timeout'] ?? null,
                    disableHibernation: $instanceParams['disable_hibernation'] ?? false,
                )),
                'Updating instance...',
            ),
        );

        if (count($environmentParams) > 0) {
            $this->loopUntilValid(
                function ($errors) use ($environmentParams, $environment) {
                    if ($errors->messageContains('global', 'wait a few seconds')) {
                        info('Waiting a few seconds...');
                        Sleep::for(CarbonInterval::seconds(5));
                    }

                    return spin(
                        fn () => $this->client->environments()->update(
                            new UpdateEnvironmentRequestData(
                                environmentId: $environment->id,
                                databaseSchemaId: $environmentParams['database_schema_id'] ?? null,
                                websocketApplicationId: $environmentParams['websocket_application_id'] ?? null,
                            ),
                        ),
                        'Updating environment...',
                    );
                },
                handleNonInteractiveErrors: function ($errors) {
                    if ($errors->messageContains('global', 'wait a few seconds')) {
                        Sleep::for(CarbonInterval::seconds(5));

                        return true;
                    }

                    return false;
                },
                shouldRetry: fn ($errors) => $errors->messageContains('global', 'wait a few seconds'),
            );
        }

        if ($queueType !== null) {
            $this->provisionQueue($queueType, $environment);
        }

        return $queueType;
    }

    protected function applyOpinionatedOptions(Environment $environment, ?string $queueType = null): void
    {
        $composer = $this->composer();

        $instanceParams = [
            'uses_scheduler' => true,
            'uses_octane' => $composer?->hasPackage('laravel/octane') ?? false,
        ];

        $databaseSchemaId = $this->provisionDatabaseOpinionated();

        $websocketAppId = $composer?->hasPackage('laravel/reverb')
            ? $this->provisionWebsocketOpinionated()
            : null;

        $this->client->instances()->update(
            new UpdateInstanceRequestData(
                instanceId: $environment->instances[0],
                usesScheduler: $instanceParams['uses_scheduler'],
                usesOctane: $instanceParams['uses_octane'],
                usesInertiaSsr: null,
                // Cloud already turns scale to zero on for new environments, but a worker on the app
                // instance stops while it sleeps, so its jobs would wait for the next web request.
                disableHibernation: in_array($queueType, ['worker', 'horizon'], true),
            ),
        );

        $this->attachToEnvironment($environment, $databaseSchemaId, $websocketAppId);

        if ($queueType !== null) {
            $this->provisionQueue($queueType, $environment);
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

        return $this->createInScope(
            'websocket_application',
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
                return $this->createInScope(
                    'websocket_cluster',
                    fn () => $this->createWebSocketCluster($this->getWebSocketClusterDefaults()),
                );
            }

            return null;
        }

        $options = $clusters->collect()->mapWithKeys(fn (WebsocketCluster $cluster) => [$cluster->id => $cluster->name]);
        $options->prepend('Create new websocket cluster', 'new');

        $cluster = select(
            label: 'Websocket cluster',
            options: $options->toArray(),
            default: $clusters->first()->id ?? null,
            required: true,
        );

        if ($cluster !== 'new') {
            return $clusters->firstWhere('id', $cluster);
        }

        return $this->createInScope(
            'websocket_cluster',
            fn () => $this->createWebSocketCluster($this->getWebSocketClusterDefaults()),
        );
    }

    protected function getDatabase(DatabaseCluster $database): ?Database
    {
        $options = collect($database->schemas)->mapWithKeys(fn (Database $schema) => [$schema->id => $schema->name]);
        $options->prepend('Create new database', 'new');

        $schema = select(
            label: 'Database',
            options: $options->toArray(),
            default: $database->schemas[0]->id ?? null,
            required: true,
        );

        if ($schema !== 'new') {
            return collect($database->schemas)->firstWhere('id', $schema);
        }

        return $this->createInScope(
            'database',
            fn () => $this->createDatabase($database),
        );
    }

    protected function getDatabaseCluster(): ?DatabaseCluster
    {
        $databasesPaginator = $this->client->databaseClusters()->include('databases')->list();
        $databases = $databasesPaginator->collect();

        if ($databases->isEmpty()) {
            warning('No databases found.');

            $createDatabase = confirm('Do you want to create a new database?');

            if ($createDatabase) {
                return $this->createInScope(
                    'database_cluster',
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
            default: $databases->first()->id ?? null,
            required: true,
        );

        if ($database !== 'new') {
            return $databases->firstWhere('id', $database);
        }

        return $this->createInScope(
            'database_cluster',
            fn () => $this->createDatabaseCluster($this->databaseClusterDefaults()),
        );
    }

    /**
     * Shipping creates several resources in one run, so each one prompts in its own form scope.
     * Sharing the form means the application's `name` and `region` answers would be reused as
     * the database cluster's, which the API then rejects.
     */
    protected function createInScope(string $scope, callable $callback): mixed
    {
        return $this->loopUntilValid(
            fn () => $this->form()->withScope($scope, $callback),
        );
    }

    protected function pushCustomEnvironmentVariables(Application $application): void
    {
        $variables = $this->localEnvironmentVariables($this->projectPath());

        if (count($variables) === 0) {
            return;
        }

        $varOptions = collect(array_keys($variables))->mapWithKeys(fn ($key) => [
            $key => $key.$this->dim(str($variables[$key])->limit(5)->prepend(' (')->append(')')),
        ]);

        $varsToAdd = multiselect(
            label: 'Add local environment variables to Cloud environment?',
            options: $varOptions->toArray(),
        );

        if (count($varsToAdd) === 0) {
            return;
        }

        $varsToAdd = collect($varsToAdd)->map(fn ($key) => ['key' => $key, 'value' => $variables[$key]]);

        task(
            label: 'Adding selected variables to Cloud environment',
            callback: function () use ($application, $varsToAdd) {
                while (count($application->environmentIds) === 0) {
                    $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
                    Sleep::for(CarbonInterval::seconds(1));
                }

                $this->client->environments()->addVariables(
                    new AddEnvironmentVariablesRequestData(
                        environmentId: $application->environmentIds[0],
                        variables: $varsToAdd->toArray(),
                    ),
                );
            },
        );
    }
}
