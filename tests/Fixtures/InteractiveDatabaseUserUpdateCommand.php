<?php

namespace Tests\Fixtures;

use App\Commands\DatabaseUserUpdate;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of database-user:update.
 */
class InteractiveDatabaseUserUpdateCommand extends DatabaseUserUpdate
{
    public function __construct()
    {
        $this->signature = str_replace('database-user:update', 'test:interactive-database-user-update', $this->signature);
        $this->aliases = [];

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
