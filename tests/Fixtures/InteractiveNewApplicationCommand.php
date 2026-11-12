<?php

namespace Tests\Fixtures;

use App\Commands\NewApplication;
use App\Prompts\Renderer;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner and agent or CI env vars suppress output,
 * so this forces the interactive, rendered path of new.
 */
class InteractiveNewApplicationCommand extends NewApplication
{
    public function __construct()
    {
        $this->signature = preg_replace('/^new/', 'test:interactive-new', $this->signature);

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
