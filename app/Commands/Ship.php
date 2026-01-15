<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\Dto\Application;
use App\Dto\Environment;
use App\Enums\CloudRegion;
use App\Git;
use Carbon\CarbonInterval;
use Dotenv\Dotenv;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class Ship extends Command
{
    use Colors;
    use HasAClient;
    use RequiresRemoteGitRepo;

    protected $signature = 'ship';

    protected $description = 'Ship the application to Laravel Cloud';

    public function handle(Git $git)
    {
        slideIn('WE MUST *SHIP*');
        intro('Shipping application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $repository = $git->remoteRepo();

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
                label: 'Select an application',
                options: $options,
            );

            if ($selectedApp !== 'new') {
                Artisan::call('deploy', [
                    'application' => $selectedApp,
                ], $this->output);

                return;
            }
        }

        $appName = text(
            label: 'Application name',
            default: $git->currentDirectoryName(),
            required: true,
        );

        $mostUsedRegion = collect($applications->data)->pluck('region')->countBy()->sortDesc()->keys()->first();
        $defaultRegion = CloudRegion::tryFrom($mostUsedRegion ?? '')?->value ?? CloudRegion::US_EAST_2->value;

        $region = select(
            label: 'Application region',
            options: collect(CloudRegion::cases())->mapWithKeys(fn (CloudRegion $region) => [$region->value => $region->label()]),
            default: $defaultRegion,
        );

        $application = dynamicSpinner(
            fn () => $this->client->createApplication($repository, $appName, $region),
            'Creating application'
        );

        if (! $application) {
            error('Failed to create application: '.($application['message'] ?? 'Unknown error'));

            exit(1);
        }

        success('Application created!');

        $application = $this->client->getApplication($application->id);
        $environment = $this->client->getEnvironment($application->defaultEnvironmentId ?? '');

        $this->pushCustomEnvironmentVariables($application);
        $this->collectOptionsToEnable($environment);

        outro(sprintf('https://cloud.laravel.com/%s/%s', $application->organizationId, $application->slug));
    }

    protected function collectOptionsToEnable(Environment $environment): void
    {
        $composer = new Composer(app('files'), getcwd());
        $enableOptions = [
            'scheduler' => 'Laravel Scheduler',
            'hibernation' => 'Hibernation',
        ];

        if ($composer->hasPackage('inertiajs/inertia-laravel')) {
            $enableOptions['inertia_ssr'] = 'Inertia SSR';
        }

        if ($composer->hasPackage('laravel/octane')) {
            $enableOptions['octane'] = 'Laravel Octane';
        }

        $selectedOptions = multiselect(
            label: 'Enable any of the following features?',
            options: $enableOptions,
        );

        if (count($selectedOptions) === 0) {
            return;
        }

        $params = [];

        if (in_array('scheduler', $selectedOptions)) {
            $params['uses_scheduler'] = true;
        }

        if (in_array('octane', $selectedOptions)) {
            $params['uses_octane'] = true;
        }

        if (in_array('inertia_ssr', $selectedOptions)) {
            $params['uses_inertia_ssr'] = true;
        }

        if (in_array('hibernation', $selectedOptions)) {
            $hibernateFor = text(
                label: 'Hibernate after',
                default: '5',
                required: true,
                validate: fn ($value) => is_numeric($value) && intval($value) >= 1 && intval($value) <= 60 ? null : 'Must be a number between 1 and 60',
                hint: 'The number of minutes without HTTP requests received before your application hibernates (1-60)',
            );

            $params['uses_sleep_mode'] = true;
            $params['sleep_timeout'] = $hibernateFor;
        }

        dynamicSpinner(
            function () use ($environment, $params) {
                $this->client->updateInstance($environment->instances[0], $params);
            },
            'Updating environment'
        );
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
