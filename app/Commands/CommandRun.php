<?php

namespace App\Commands;

use App\Client\Requests\RunCommandRequestData;
use App\Concerns\InteractsWithClipbboard;
use App\Dto\Command;
use App\Enums\CommandStatus;
use App\Prompts\MonitorCommand;
use App\Support\ValueResolver;
use Carbon\CarbonInterval;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\text;

class CommandRun extends BaseCommand
{
    protected ?string $jsonDataClass = Command::class;

    use InteractsWithClipbboard;

    protected $signature = 'command:run
                            {environment? : The environment ID}
                            {--cmd= : The command to run}
                            {--no-monitor : Do not monitor the command in real-time}
                            {--copy-output : Copy the output to the clipboard}';

    protected $description = 'Run a command on an environment';

    public function handle()
    {
        $this->ensureClient();

        intro('Running Command');

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));
        $command = $this->loopUntilValid(fn () => $this->runCommandOnEnvironment($environment->id));

        if ($this->option('no-monitor')) {
            $this->outputJsonIfWanted($command);

            return self::SUCCESS;
        }

        if (! $this->isInteractive()) {
            $command = $this->monitorNonInteractively($command);

            $this->outputJsonIfWanted($command);

            if ($this->option('copy-output')) {
                $this->copyToClipboard($command->output ?? '');
            }

            return $command->status === CommandStatus::SUCCESS ? self::SUCCESS : self::FAILURE;
        }

        $this->outputJsonIfWanted($command);

        (new MonitorCommand(
            fn (string $id) => $this->client->commands()->get($id),
            $command,
        ))->display();

        if ($this->option('copy-output')) {
            $command = $this->client->commands()->get($command->id);
            $this->copyToClipboard($command->output ?? '');
            success('Output copied to clipboard');
        }
    }

    protected function monitorNonInteractively(Command $command): Command
    {
        $checkInterval = 3;
        $lastStatus = '';

        while (true) {
            $command = $this->client->commands()->get($command->id);

            $currentStatus = $command->status->label();

            if ($currentStatus !== $lastStatus) {
                $this->writeJsonIfWanted([
                    'command_id' => $command->id,
                    'status' => $command->status->value,
                    'message' => $currentStatus,
                ]);
                $lastStatus = $currentStatus;
            }

            if ($command->isFinished()) {
                return $command;
            }

            Sleep::for(CarbonInterval::seconds($checkInterval));
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
