<?php

namespace App\Commands;

use App\Client\Requests\UpdateBackgroundProcessRequestData;
use App\Dto\BackgroundProcess;
use App\Support\UpdateFields;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
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

        $fields = $this->getFieldDefinitions($process);

        $data = [];

        foreach ($fields as $optionName => $field) {
            if ($this->option($optionName)) {
                $value = $this->option($optionName);
                $data[$field['key']] = $field['key'] === 'instances' ? (int) $value : $value;

                $this->reportChange(
                    $field['label'],
                    (string) $field['current'],
                    (string) $value,
                );
            }
        }

        $updatedProcess = $this->resolveUpdatedProcess($process, $fields, $data);

        $this->outputJsonIfWanted($updatedProcess);

        success('Background process updated');

        outro("Background process updated: {$updatedProcess->id}");
    }

    protected function resolveUpdatedProcess(BackgroundProcess $process, array $fields, array $data): BackgroundProcess
    {
        if (! $this->isInteractive()) {
            if (empty($data)) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                exit(self::FAILURE);
            }

            return $this->updateProcess($process, $data);
        }

        if (empty($data)) {
            return $this->loopUntilValid(
                fn () => $this->collectDataAndUpdate($fields, $process),
            );
        }

        if (! $this->shouldRunUpdateFromOptions()) {
            error('Update cancelled');

            exit(self::FAILURE);
        }

        return $this->updateProcess($process, $data);
    }

    protected function updateProcess(BackgroundProcess $process, array $data): BackgroundProcess
    {
        spin(
            fn () => $this->client->backgroundProcesses()->update(new UpdateBackgroundProcessRequestData(
                backgroundProcessId: $process->id,
                command: isset($data['command']) ? (string) $data['command'] : null,
                instances: array_key_exists('instances', $data) ? (int) $data['instances'] : null,
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

    protected function getFieldDefinitions(BackgroundProcess $process): array
    {
        $fields = new UpdateFields;

        $fields->add('command', fn ($value) => $this->getNewCommand($value))->currentValue($process->command);
        $fields->add('instances', fn ($value) => $this->getNewInstances($value))->currentValue($process->instances);

        return $fields->get();
    }

    protected function collectDataAndUpdate(array $fields, BackgroundProcess $process): BackgroundProcess
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($fields)->mapWithKeys(fn ($field, $key) => [
                $key => $field['label'],
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            exit(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $field = $fields[$optionName];

            $this->fields()->add($field['key'], fn ($resolver) => $resolver->fromInput(
                fn ($value) => ($field['prompt'])($value ?? $field['current']),
            ));
        }

        $params = $this->fields()->all();

        if (isset($params['instances'])) {
            $params['instances'] = (int) $params['instances'];
        }

        return $this->updateProcess($process, $params);
    }

    protected function getNewCommand(string $oldCommand): string
    {
        return text(
            label: 'Command',
            required: true,
            default: $oldCommand,
        );
    }

    protected function getNewInstances(int|string $oldInstances): int
    {
        $value = text(
            label: 'Instances',
            required: true,
            default: (string) $oldInstances,
            validate: fn ($value) => match (true) {
                ! is_numeric($value) => 'Instances must be a number',
                (int) $value < 1 => 'Instances must be at least 1',
                default => null,
            },
        );

        return (int) $value;
    }
}
