<?php

namespace Tests\Fixtures;

use App\Commands\InstanceGet;
use App\Prompts\Renderer;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner and agent or CI env vars suppress output,
 * so this forces the interactive, rendered path of instance:get.
 */
class InteractiveInstanceGetCommand extends InstanceGet
{
    public function __construct()
    {
        $this->signature = str_replace('instance:get', 'test:interactive-instance-get', $this->signature);

        parent::__construct();
    }

    protected function isInteractive(): bool
    {
        return true;
    }

    protected function configurePrompts(InputInterface $input): void
    {
        Renderer::$suppressOutput = false;

        parent::configurePrompts($input);

        Prompt::interactive(true);
    }
}
