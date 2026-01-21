<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Concerns\Validates;
use App\Dto\Application;
use App\Dto\Database;
use App\Dto\DatabaseCluster;
use App\Dto\DatabaseType;
use App\Dto\Environment;
use App\Dto\ValidationErrors;
use App\Enums\CloudRegion;
use App\Git;
use Carbon\CarbonInterval;
use Dotenv\Dotenv;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class Ship extends BaseCommand
{
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
        $this->intro('Shipping application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $repository = $this->git->remoteRepo();

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Checking for existing application...'
        );

        $existingApps = collect($applications->data)->filter(
            fn (Application $app) => $app->repositoryFullName === $repository
        );

        if ($existingApps->isNotEmpty()) {
            info('Found '.$existingApps->count().' existing applications for this repository.');

            $options = $existingApps->mapWithKeys(fn (Application $app) => [$app->id => 'Deploy '.$app->name]);
            $options->prepend('Create new application', 'new');

            $selectedApp = select(
                label: 'Application',
                options: $options,
            );

            if ($selectedApp !== 'new') {
                Artisan::call('deploy', [
                    'application' => $selectedApp,
                ], $this->output);

                return;
            }
        }

        $mostUsedRegion = collect($applications->data)->pluck('region')->countBy()->sortDesc()->keys()->first();
        $defaultRegion = CloudRegion::tryFrom($mostUsedRegion ?? '')?->value ?? CloudRegion::US_EAST_2->value;

        $application = $this->loopUntilValid(
            fn ($errors) => $this->createApplication($errors, $defaultRegion, $repository),
        );

        success('Application created!');

        $application = $this->client->getApplication($application->id);
        $environment = $this->client->getEnvironment($application->defaultEnvironmentId ?? '');

        $this->loopUntilValid(
            fn () => $this->pushCustomEnvironmentVariables($application),
        );

        $this->loopUntilValid(
            fn () => $this->collectOptionsToEnable($environment),
        );

        $this->outro(sprintf('https://cloud.laravel.com/%s/%s', $application->organizationId, $application->slug));

        $deploy = confirm('Do you want to deploy the application?');

        if (! $deploy) {
            return;
        }

        if (confirm('Do you want to edit the build and deploys commands before deploying?')) {
            $this->updateCommands($environment);
        }

        Artisan::call('deploy', [
            'application' => $application->id,
        ], $this->output);
    }

    protected function createApplication(ValidationErrors $errors, string $defaultRegion, string $repository): ?Application
    {
        if (! $this->appName || $errors->has('name')) {
            $this->appName = text(
                label: 'Application name',
                default: $this->appName ?? $this->git->currentDirectoryName(),
                required: true,
            );
        }

        if (! $this->region || $errors->has('region')) {
            $this->region = select(
                label: 'Application region',
                options: collect(CloudRegion::cases())->mapWithKeys(
                    fn (CloudRegion $region) => [
                        $region->value => $region->label(),
                    ],
                ),
                default: $this->region ?? $defaultRegion,
            );
        }

        $application = dynamicSpinner(
            fn () => $this->client->createApplication(
                $repository,
                $this->appName,
                $this->region,
            ),
            'Creating application',
        );

        return $application;
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
                validate: fn ($value) => is_numeric($value) && intval($value) >= 1 && intval($value) <= 60 ? null : 'Must be a number between 1 and 60',
                hint: 'The number of minutes without HTTP requests received before your application hibernates (1-60)',
            );

            $instanceParams['sleep_timeout'] = $hibernateFor;
        }

        if (in_array('database', $selectedOptions)) {
            $cluster = $this->getDatabaseCluster();

            if ($cluster) {
                $cluster = $this->client->getDatabase($cluster->id);
                $database = $this->getDatabase($cluster);
                $environmentParams['database_schema_id'] = $database->id;
            }
        }

        if (count($instanceParams) > 0) {
            $this->loopUntilValid(
                fn () => spin(
                    fn () => $this->client->updateInstance($environment->instances[0], $instanceParams),
                    'Updating instance...',
                ),
            );
        }

        if (count($environmentParams) > 0) {
            $this->loopUntilValid(
                function ($errors) use ($environmentParams, $environment) {
                    if ($errors->messageContains('global', 'wait a few seconds')) {
                        Sleep::for(CarbonInterval::seconds(5));
                    }

                    return spin(fn () => $this->client->updateEnvironment($environment->id, $environmentParams), 'Updating environment...');
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
            validate: function ($value) {
                if (! preg_match('/^[a-z0-9_-]+$/', $value)) {
                    return 'Must contain only lowercase letters, numbers, and underscores';
                }

                if (strlen($value) < 3 || strlen($value) > 40) {
                    return 'Must be between 3 and 40 characters';
                }

                return null;
            },
        );

        return $this->client->createDatabase($database->id, $name);
    }

    protected function getDatabaseCluster(): ?DatabaseCluster
    {
        $databases = $this->client->listDatabases();

        if (count($databases->data) === 0) {
            info('No databases found!');

            $createDatabase = confirm('Do you want to create a new database?');

            if ($createDatabase) {
                return $this->createDatabase();
            }

            return null;
        }

        $options = collect($databases->data)->mapWithKeys(fn (DatabaseCluster $database) => [$database->id => $database->name]);
        $options->prepend('Create new database cluster', 'new');

        $database = select(
            label: 'Database cluster',
            options: $options,
            default: $databases->data[0]?->id ?? null,
            required: true,
        );

        if ($database !== 'new') {
            return collect($databases->data)->firstWhere('id', $database);
        }

        return $this->createDatabase();
    }

    protected function createDatabase(): ?DatabaseCluster
    {
        $name = text(
            label: 'Database cluster name',
            required: true,
            default: str($this->appName)->snake()->replace('-', '_')->toString(),
            validate: fn ($value) => preg_match('/^[a-zA-Z0-9_]+$/', $value) ? null : 'Must contain only letters, numbers and underscores',
        );

        info('More information about Cloud Database Clusters: https://cloud.laravel.com/docs/resources/databases');

        $types = $this->client->listDatabaseTypes();

        $selectedType = select(
            label: 'Database cluster type',
            options: collect($types)->mapWithKeys(fn (DatabaseType $type) => [$type->type => $type->label]),
            required: true,
        );

        $type = collect($types)->firstWhere('type', $selectedType);

        $regions = collect($type->regions)->mapWithKeys(fn (string $region) => [$region => CloudRegion::from($region)->label()]);

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
                    $application = $this->client->getApplication($application->id);
                    Sleep::for(CarbonInterval::seconds(1));
                }

                $this->client->replaceEnvironmentVariables($application->environmentIds[0], $varsToAdd->toArray());
            },
            'Adding selected variables to Cloud environment'
        );
    }
}
