<?php

namespace Tests\Fixtures;

use App\Commands\EnvironmentUpdate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of environment:update.
 */
class InteractiveEnvironmentUpdateCommand extends EnvironmentUpdate
{
    public function __construct()
    {
        $this->signature = str_replace('environment:update', 'test:interactive-environment-update', $this->signature);

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
