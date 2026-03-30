<?php

namespace App\Commands;

use App\Client\Requests\RunCommandRequestData;
use App\Concerns\InteractsWithClipbboard;
use App\Dto\Command;
use App\Prompts\MonitorCommand;
use App\Support\ValueResolver;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CommandRun extends BaseCommand
{
    use InteractsWithClipbboard;

    protected $signature = 'command:run
                            {environment? : The environment ID}
                            {--cmd= : The command to run}
                            {--no-monitor : Do not monitor the command in real-time}
                            {--copy-output : Copy the output to the clipboard}
                            {--json : Output as JSON}';

    protected $description = 'Run a command on an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Running Command');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $command = $this->loopUntilValid(fn () => $this->runCommandOnEnvironment($environment->id));

        $this->outputJsonIfWanted($command);

        if (! $this->option('no-monitor')) {
            (new MonitorCommand(
                fn (string $id) => $this->client->commands()->get($id),
                $command,
            ))->display();
        }

        if ($this->option('copy-output')) {
            if ($this->option('no-monitor')) {
                warning('Output cannot be copied when monitoring is disabled');

                return self::SUCCESS;
            }

            $command = $this->client->commands()->get($command->id);
            $this->copyToClipboard($command->output ?? '');
            success('Output copied to clipboard');
        }
    }

    protected function runCommandOnEnvironment(string $environmentId): Command
    {
        $this->form()->prompt(
            'command',
            fn (ValueResolver $resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Command',
                    default: $value ?? 'php artisan ',
                    required: true,
                ),
            ),
            'cmd',
        );

        return dynamicSpinner(
            fn () => $this->client->commands()->run(
                new RunCommandRequestData(
                    environmentId: $environmentId,
                    command: $this->form()->get('command'),
                ),
            ),
            'Running command...',
        );
    }
}
