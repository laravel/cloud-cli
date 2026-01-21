<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresRemoteGitRepo;
use App\Concerns\UpdatesBuildDeployCommands;
use App\Concerns\Validates;
use App\Prompts\Hyb as HybPrompt;

class Hyb extends BaseCommand
{
    use HasAClient;
    use RequiresRemoteGitRepo;
    use UpdatesBuildDeployCommands;
    use Validates;

    protected $signature = 'hyb';

    protected $description = 'hyb';

    public function handle()
    {
        $this->newLine();
        (new HybPrompt)->animate();
        $this->newLine();
    }
}
