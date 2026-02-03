<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Dto\Command;
use App\Prompts\MonitorCommand;
use App\Support\ValueResolver;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;

class CommandRun extends BaseCommand
{
    use HasAClient;

    protected $signature = 'command:run
                            {environment? : The environment ID}
                            {--command= : The command to run}
                            {--monitor : Monitor the command in real-time}
                            {--json : Output as JSON}';

    protected $description = 'Run a command on an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Running Command');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $command = $this->loopUntilValid(fn () => $this->runCommandOnEnvironment($environment->id));

        $this->outputJsonIfWanted($command);

        if ($this->option('monitor')) {
            (new MonitorCommand(
                fn (string $id) => $this->client->commands()->get($id),
                $command,
            ))->display();
        }

        outro('Command queued');
    }

    protected function runCommandOnEnvironment(string $environmentId): Command
    {
        $this->addParam(
            'command',
            fn (ValueResolver $resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Command',
                    default: $value ?? 'php artisan ',
                    required: true,
                ),
            ),
        );

        return dynamicSpinner(
            fn () => $this->client->commands()->run(
                $environmentId,
                $this->getParam('command'),
            ),
            'Running command...',
        );
    }
}
