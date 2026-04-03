<?php

namespace App\Commands;

use App\Concerns\RequiresRemoteGitRepo;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class Browser extends BaseCommand
{
    use RequiresRemoteGitRepo;

    protected $signature = 'browser
                            {application? : The application ID or name}
                            {environment? : The name of the environment to deploy} ';

    protected $description = 'Open the application in the browser';

    public function handle()
    {
        intro('Opening Site in Browser');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $app = $this->resolvers()->application()->from($this->argument('application'));
        $environment = $this->resolvers()->environment()->withApplication($app)->from($this->argument('environment'));

        if (! $environment->url) {
            warning('No site found for this environment.');

            return self::FAILURE;
        }

        openUrl($environment->url);

        outro($environment->url);
    }
}
