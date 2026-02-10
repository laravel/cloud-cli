<?php

namespace App\Commands;

use App\Client\Requests\UpdateBackgroundProcessRequestData;
use App\Dto\BackgroundProcess;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BackgroundProcessUpdate extends BaseCommand
{
    protected $signature = 'background-process:update
                            {process? : The background process ID}
                            {--command= : The command to run}
                            {--instances= : Number of instances}
                            {--force : Force update without confirmation}
                            {--json : Output as JSON}';

    protected $description = 'Update a background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Background Process');

        $process = $this->resolvers()->backgroundProcess()->from($this->argument('process'));

        $this->defineFields($process);

        foreach ($this->form()->filled() as $key => $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updatedProcess = $this->resolveUpdatedProcess($process);

        $this->outputJsonIfWanted($updatedProcess);

        success('Background process updated');

        outro("Background process updated: {$updatedProcess->id}");
    }

    protected function resolveUpdatedProcess(BackgroundProcess $process): BackgroundProcess
    {
        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                exit(self::FAILURE);
            }

            return $this->updateProcess($process);
        }

        if (! $this->form()->hasAnyValues()) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($process),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            exit(self::FAILURE);
        }

        return $this->updateProcess($process);
    }

    protected function updateProcess(BackgroundProcess $process): BackgroundProcess
    {
        $instances = $this->form()->get('instances');

        spin(
            fn () => $this->client->backgroundProcesses()->update(new UpdateBackgroundProcessRequestData(
                backgroundProcessId: $process->id,
                command: $this->form()->get('command'),
                instances: $instances !== null ? (int) $instances : null,
            )),
            'Updating background process...',
        );

        return $this->client->backgroundProcesses()->get($process->id);
    }

    protected function shouldRunUpdateFromOptions(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return confirm('Update the background process?');
    }

    protected function defineFields(BackgroundProcess $process): void
    {
        $this->form()->define(
            'command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Command',
                    required: true,
                    default: $value ?? $process->command,
                ),
            ),
        )->setPreviousValue($process->command);

        $this->form()->define(
            'instances',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => $this->getNewInstances($value ?? $process->instances),
            ),
        )->setPreviousValue((string) $process->instances);
    }

    protected function collectDataAndUpdate(BackgroundProcess $process): BackgroundProcess
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            exit(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateProcess($process);
    }

    protected function getNewInstances(int|string $oldInstances): int
    {
        return number(
            label: 'Instances',
            required: true,
            default: (string) $oldInstances,
            min: 1,
        );
    }
}
