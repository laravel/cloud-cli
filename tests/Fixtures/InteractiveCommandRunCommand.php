<?php

namespace Tests\Fixtures;

use App\Commands\CommandRun;
use App\Prompts\Renderer;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner and agent or CI env vars suppress output,
 * so this forces the interactive, rendered path of command:run.
 */
class InteractiveCommandRunCommand extends CommandRun
{
    protected $aliases = [];

    public function __construct()
    {
        $this->signature = str_replace('command:run', 'test:interactive-command-run', $this->signature);

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
