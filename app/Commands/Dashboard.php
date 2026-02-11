<?php

namespace App\Commands;

use App\Concerns\RequiresRemoteGitRepo;
use Illuminate\Support\Facades\Process;

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

        // TODO: Deal with the correct environment
        $app = $this->resolvers()->application()->from($this->argument('application'));
        $url = $app->url();

        Process::run('open '.$url);

        outro($url);
    }
}
