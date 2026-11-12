<?php

namespace Tests\Fixtures;

use App\Commands\DatabaseUserCreate;
use App\Prompts\Renderer;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\InputInterface;

/**
 * STDIN is not a TTY under the test runner, so this forces the interactive path of database-user:create.
 */
class InteractiveDatabaseUserCreateCommand extends DatabaseUserCreate
{
    public function __construct()
    {
        $this->signature = str_replace('database-user:create', 'test:interactive-database-user-create', $this->signature);
        $this->aliases = [];

        parent::__construct();
    }

    protected function isInteractive(): bool
    {
        return true;
    }

    protected function configurePrompts(InputInterface $input): void
    {
        // The test runner can look like an agent or CI, which hides the human output under test.
        Renderer::$suppressOutput = false;

        parent::configurePrompts($input);

        Prompt::interactive(true);
    }
}
