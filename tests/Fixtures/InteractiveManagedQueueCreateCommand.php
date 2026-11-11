<?php

namespace Tests\Fixtures;

use App\Commands\ManagedQueueCreate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of managed-queue:create.
 */
class InteractiveManagedQueueCreateCommand extends ManagedQueueCreate
{
    public function __construct()
    {
        $this->signature = str_replace('managed-queue:create', 'test:interactive-managed-queue-create', $this->signature);

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
