<?php

namespace Tests\Fixtures;

use App\Commands\Deploy;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of deploy.
 */
class InteractiveDeployCommand extends Deploy
{
    public function __construct()
    {
        $this->signature = preg_replace('/^deploy/', 'test:interactive-deploy', $this->signature);

        parent::__construct();
    }

    protected function isInteractive(): bool
    {
        return true;
    }

    protected function configurePrompts(InputInterface $input): void
    {
        parent::configurePrompts($input);

        Prompt::interactive(true);
    }
}
