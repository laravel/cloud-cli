<?php

namespace App\Commands;

use App\Concerns\RequiresRemoteGitRepo;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class Dashboard extends BaseCommand
{
    use RequiresRemoteGitRepo;

    protected $signature = 'dashboard
                            {application? : The application ID or name}';

    protected $description = 'Open the application in the Cloud dashboard';

    public function handle()
    {
        intro('Opening Cloud Dashboard');

        $this->ensureClient();
        $this->ensureRemoteGitRepo();

        $environment = $this->resolvers()->environment()->include('application')->resolve();
        $application = $this->client->applications()->get($environment->application->id);

        $url = $application->url($environment);

        openUrl($url);

        outro($url);
    }
}
