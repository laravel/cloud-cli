<?php

namespace App\Commands;

use App\Client\Requests\CreateScheduledCommandRequestData;
use App\Dto\EnvironmentInstance;
use App\Dto\ScheduledCommand;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ScheduledCommandCreate extends BaseCommand
{
    protected ?string $jsonDataClass = ScheduledCommand::class;

    protected $signature = 'scheduled-command:create
                            {instance? : The instance ID}
                            {--name= : A friendly name for the scheduled command}
                            {--command= : The command to run}
                            {--schedule= : A five-field cron expression, evaluated in UTC (e.g. "0 0 * * *" or @daily)}';

    protected $description = 'Create a new scheduled command';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Scheduled Command');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $scheduledCommand = $this->loopUntilValid(fn () => $this->createScheduledCommand($instance));

        $this->outputJsonIfWanted($scheduledCommand);

        success("Scheduled command created: {$scheduledCommand->id}");
    }

    protected function createScheduledCommand(EnvironmentInstance $instance): ScheduledCommand
    {
        $this->form()->prompt(
            'command',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Command',
                    default: $value ?? 'php artisan ',
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'schedule',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Schedule',
                    placeholder: '0 0 * * *',
                    default: $value ?? '',
                    required: true,
                    hint: 'A five-field cron expression, evaluated in UTC. @hourly, @daily, @weekly, @monthly, and @yearly also work.',
                ),
            ),
        );

        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Name',
                    default: $value ?? '',
                    hint: 'Optional',
                ))
                // The name is optional, so an empty default keeps headless runs from failing.
                ->nonInteractively(fn () => ''),
        );

        return spin(
            fn () => $this->client->scheduledCommands()->create(
                new CreateScheduledCommandRequestData(
                    instanceId: $instance->id,
                    command: $this->form()->get('command'),
                    schedule: $this->form()->get('schedule'),
                    name: $this->form()->get('name') ?: null,
                ),
            ),
            'Creating scheduled command...',
        );
    }
}
