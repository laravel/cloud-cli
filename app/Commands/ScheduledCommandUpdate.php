<?php

namespace App\Commands;

use App\Client\Requests\UpdateScheduledCommandRequestData;
use App\Dto\ScheduledCommand;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ScheduledCommandUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = ScheduledCommand::class;

    protected $signature = 'scheduled-command:update
                            {scheduled-command? : The scheduled command ID}
                            {--name= : A friendly name for the scheduled command}
                            {--command= : The command to run}
                            {--schedule= : A five-field cron expression, evaluated in UTC (e.g. "0 0 * * *" or @daily)}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a scheduled command';

    public function handle()
    {
        $this->ensureClient();

        intro('Updating Scheduled Command');

        $scheduledCommand = $this->resolvers()->scheduledCommand()->from($this->argument('scheduled-command'));

        $this->defineFields($scheduledCommand);

        foreach ($this->form()->filled() as $value) {
            $this->reportChange(
                $value->label(),
                $value->previousValue(),
                $value->value(),
            );
        }

        $updated = $this->runUpdate(
            fn () => $this->updateScheduledCommand($scheduledCommand),
            fn () => $this->collectDataAndUpdate($scheduledCommand),
        );

        $this->outputJsonIfWanted($updated);

        success("Scheduled command updated: {$updated->id}");
    }

    protected function updateScheduledCommand(ScheduledCommand $scheduledCommand): ScheduledCommand
    {
        return spin(
            fn () => $this->client->scheduledCommands()->update(
                new UpdateScheduledCommandRequestData(
                    scheduledCommandId: $scheduledCommand->id,
                    name: $this->form()->get('name'),
                    command: $this->form()->get('command'),
                    schedule: $this->form()->get('schedule'),
                ),
            ),
            'Updating scheduled command...',
        );
    }

    protected function defineFields(ScheduledCommand $scheduledCommand): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Name',
                    default: $value ?? $scheduledCommand->name ?? '',
                ),
            ),
        )->setPreviousValue($scheduledCommand->name);

        $this->form()->define(
            'command',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Command',
                    required: true,
                    default: $value ?? $scheduledCommand->command,
                ),
            ),
        )->setPreviousValue($scheduledCommand->command);

        $this->form()->define(
            'schedule',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Schedule',
                    required: true,
                    default: $value ?? $scheduledCommand->schedule,
                    hint: 'A five-field cron expression, evaluated in UTC. @hourly, @daily, @weekly, @monthly, and @yearly also work.',
                ),
            ),
        )->setPreviousValue($scheduledCommand->schedule);
    }

    protected function collectDataAndUpdate(ScheduledCommand $scheduledCommand): ScheduledCommand
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateScheduledCommand($scheduledCommand);
    }
}
