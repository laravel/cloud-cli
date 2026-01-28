<?php

namespace App\Commands;

use App\Concerns\HandlesAvatars;
use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Concerns\Validates;
use App\Dto\Application;
use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Dto\Environment;
use App\Dto\Region;
use App\Dto\ValidationErrors;
use App\Git;
use Carbon\CarbonInterval;
use Dotenv\Dotenv;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
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

class Ship extends BaseCommand
{
    use HandlesAvatars;
    use HasAClient;
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
            fn () => $this->client->applications()->include('organization', 'environments', 'defaultEnvironment')->list(),
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
                Artisan::call('deploy', [
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

        $application = $this->client->applications()->include('organization', 'environments', 'defaultEnvironment')->get($application->id);
        $environment = $this->client->environments()->include('instances')->get($application->defaultEnvironmentId ?? '');

        $this->loopUntilValid(
            fn () => $this->pushCustomEnvironmentVariables($application),
        );

        $this->loopUntilValid(
            fn () => $this->collectOptionsToEnable($environment),
        );

        outro($application->url());

        if (! confirm('Do you want to deploy the application?')) {
            return;
        }

        if (confirm('Do you want to edit the build and deploy commands before deploying?')) {
            $this->updateCommands($environment);
        }

        Artisan::call('deploy', [
            'application' => $application->id,
        ], $this->output);

        if (confirm('Open site in browser?')) {
            spin(
                fn () => $this->waitForUrlToBeReady($environment),
                'Waiting for site to be ready...',
            );

            Process::run('open '.$environment->url);
        }
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
            Sleep::for(CarbonInterval::seconds(1));
        } while ($response->status() !== 200);

        return true;
    }

    protected function createApplication(ValidationErrors $errors, string $defaultRegion, string $repository): ?Application
    {
        $this->addParam(
            'name',
            fn ($resolver) => $resolver->fromInput(fn ($value) => text(
                label: 'Application name',
                default: $value ?? $this->git->currentDirectoryName(),
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
            'database' => 'Database Cluster',
        ];

        $packages = [
            'inertiajs/inertia-laravel' => ['inertia_ssr' => 'Inertia SSR'],
            'laravel/octane' => ['octane' => 'Laravel Octane'],
            'laravel/reverb' => ['reverb' => 'Laravel Reverb'],
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

        if (in_array('database', $selectedOptions)) {
            $cluster = $this->getDatabaseCluster();

            if ($cluster) {
                $cluster = $this->client->databaseClusters()->include('schemas')->get($cluster->id);
                $database = $this->getDatabase($cluster);
                $environmentParams['database_schema_id'] = $database->id;
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

        $name = text(
            label: 'Database name',
            required: true,
            validate: fn ($value) => match (true) {
                ! preg_match('/^[a-z0-9_-]+$/', $value) => 'Must contain only lowercase letters, numbers, and underscores',
                strlen($value) < 3 => 'Must be at least 3 characters',
                strlen($value) > 40 => 'Must be less than 40 characters',
                default => null,
            },
        );

        return $this->client->databases()->create($database->id, $name);
    }

    protected function getDatabaseCluster(): ?DatabaseCluster
    {
        $databasesPaginator = $this->client->databaseClusters()->include('schemas')->list();
        $databases = $databasesPaginator->collect();

        if ($databases->isEmpty()) {
            info('No databases found!');

            $createDatabase = confirm('Do you want to create a new database?');

            if ($createDatabase) {
                return $this->createDatabase();
            }

            return null;
        }

        $options = $databases->mapWithKeys(fn (DatabaseCluster $database) => [$database->id => $database->name]);
        $options->prepend('Create new database cluster', 'new');

        $database = select(
            label: 'Database cluster',
            options: $options,
            default: $databases->first()?->id ?? null,
            required: true,
        );

        if ($database !== 'new') {
            return $databases->firstWhere('id', $database);
        }

        return $this->createDatabase();
    }

    protected function createDatabase(): ?DatabaseCluster
    {
        $name = text(
            label: 'Database cluster name',
            required: true,
            default: str($this->appName)->snake()->replace('-', '_')->toString(),
            validate: fn ($value) => match (true) {
                ! preg_match('/^[a-zA-Z0-9_]+$/', $value) => 'Must contain only letters, numbers and underscores',
                default => null,
            },
        );

        info('More information about Cloud Database Clusters: https://cloud.laravel.com/docs/resources/databases');

        $types = $this->client->databaseClusters()->types();

        $selectedType = select(
            label: 'Database cluster type',
            options: collect($types)->mapWithKeys(fn (DatabaseType $type) => [$type->type => $type->label]),
            required: true,
        );

        $type = collect($types)->firstWhere('type', $selectedType);

        $regions = spin(
            fn () => $this->client->meta()->regions(),
            'Fetching regions...',
        );

        $region = select(
            label: 'Database cluster region',
            options: $regions,
            required: true,
            default: in_array($this->region, $type->regions) ? $this->region : null,
        );

        dd($name, $type, $region);
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
                    $application = $this->client->applications()->include('organization', 'environments', 'defaultEnvironment')->get($application->id);
                    Sleep::for(CarbonInterval::seconds(1));
                }

                $this->client->environments()->replaceVariables($application->environmentIds[0], $varsToAdd->toArray());
            },
            'Adding selected variables to Cloud environment',
        );
    }
}
