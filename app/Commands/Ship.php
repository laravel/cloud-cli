<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\ConfigRepository;
use App\Dto\Application;
use App\Enums\CloudRegion;
use App\Git;
use Carbon\CarbonInterval;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;
use Throwable;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
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

    public function handle(ConfigRepository $config, Git $git)
    {
        $this->newLine();
        slideIn('WE MUST *SHIP*');
        $this->newLine();

        intro('Shipping application to Laravel Cloud');

        $this->ensureClient();
        $this->ensureRemoteGitRepo($git);
        $this->createCloudApplication($git);
    }

    protected function createCloudApplication(Git $git): void
    {
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

            $selection = select(
                label: 'Select an application',
                options: $options,
            );

            if ($selection !== 'new') {
                Artisan::call('deploy', [
                    'application' => $selection,
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

        $application = spin(
            fn () => $this->client->createApplication($repository, $appName, $region),
            'Creating application...'
        );

        if ($application) {
            success('Application created!');
        } else {
            error('Failed to create application: '.($application['message'] ?? 'Unknown error'));

            exit(1);
        }

        $application = $this->client->getApplication($application->id);

        $envPath = getcwd().'/.env';

        if (file_exists($envPath)) {
            try {
                $variables = Dotenv::parse(file_get_contents($envPath));
            } catch (Throwable $e) {
                //
            }

            $diff = array_diff(array_keys($variables), config('env.laravel'));

            if (count($diff) > 0) {
                $diffVariables = collect($diff)->mapWithKeys(fn ($key) => [
                    $key => $key.$this->dim(str($variables[$key])->limit(5)->prepend(' (')->append(')')),
                ]);
                $varsToAdd = multiselect('Add local environment variables to Cloud environment?', options: $diffVariables);

                if (count($varsToAdd) > 0) {
                    $varsToAdd = collect($varsToAdd)->mapWithKeys(fn ($key) => [$key => $variables[$key]]);

                    spin(
                        function () use ($application, $varsToAdd) {
                            while (count($application->environmentIds) === 0) {
                                $application = $this->client->getApplication($application->id);
                                Sleep::for(CarbonInterval::seconds(1));
                            }

                            $this->client->replaceEnvironmentVariables($application->environmentIds[0], $varsToAdd->toArray());
                        },
                        'Adding selected variables to Cloud environment...'
                    );
                }
            }
        }

        info(sprintf('https://cloud.laravel.com/%s/%s', $application->organizationId, $application->slug));
    }
}
