<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Concerns\RequiresRemoteGitRepo;
use App\Git;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class Open extends Command
{
    use Colors;
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;
    use RequiresRemoteGitRepo;

    protected $signature = 'open '
        .'{application? : The application ID or name} '
        .'{environment? : The name of the environment to deploy} ';

    protected $description = 'Open the site in the browser';

    public function handle()
    {
        intro('Opening site in browser');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $repository = app(Git::class)->remoteRepo();

        $applications = spin(
            fn () => $this->client->listApplications(),
            'Checking for existing application...'
        );

        $existingApps = collect($applications->data ?? [])->filter(
            fn ($app) => $app->repositoryFullName === $repository
        );

        if ($existingApps->isEmpty()) {
            warning('No existing Cloud application found for this repository.');

            $shouldShip = confirm('Do you want to ship this application to Laravel Cloud?');

            if ($shouldShip) {
                Artisan::call('ship', [], $this->output);

                return;
            }

            error('Cancelled.');

            exit(1);
        }

        $app = $this->getCloudApplication($existingApps);

        $environments = spin(
            fn () => $this->client->listEnvironments($app->id),
            'Checking for existing environments...'
        );

        $environment = $this->getEnvironment(collect($environments->data));

        if ($environment->url) {
            Process::run('open '.$environment->url);

            outro($environment->url);
        } else {
            outro('No site found for this environment.');
        }
    }
}
