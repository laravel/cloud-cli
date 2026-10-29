<?php

namespace App\Resolvers;

use App\Dto\ScheduledCommand;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class ScheduledCommandResolver extends Resolver
{
    use HasAnApplication;

    public function from(?string $idOrName = null): ?ScheduledCommand
    {
        $scheduledCommand = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $scheduledCommand) {
            $this->failAndExit('Unable to resolve scheduled command: '.($idOrName ?? 'Provide a valid scheduled command ID.').'. Run `cloud scheduled-command:list --json` to see available scheduled commands.');
        }

        $this->displayResolved('Scheduled Command', $scheduledCommand->name ?? $scheduledCommand->command, $scheduledCommand->id);

        return $scheduledCommand;
    }

    public function fromIdentifier(string $identifier): ?ScheduledCommand
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->scheduledCommands()->get($identifier),
                'Fetching scheduled command...',
            ),
        );
    }

    public function fromInput(): ?ScheduledCommand
    {
        $instance = $this->resolvers()
            ->instance()
            ->withApplication($this->application())
            ->resolve();

        $scheduledCommands = $this->client->scheduledCommands()->list($instance->id)->collect();

        if ($scheduledCommands->isEmpty()) {
            $this->failAndExit('No scheduled commands found for instance '.$instance->name);
        }

        if ($scheduledCommands->hasSole()) {
            return $scheduledCommands->first();
        }

        $options = $scheduledCommands->mapWithKeys(fn (ScheduledCommand $scheduledCommand) => [
            $scheduledCommand->id => str($scheduledCommand->name ?? $scheduledCommand->command)->limit(50)->toString()." ({$scheduledCommand->schedule})",
        ])->toArray();

        $this->ensureInteractive('Multiple scheduled commands found. Provide a scheduled command ID.', ['options' => $options]);

        $selected = select(
            label: 'Scheduled Command',
            options: $options,
            info: fn ($id) => $id,
        );

        // The select above already shows the chosen command.
        $this->displayResolved = false;

        return $scheduledCommands->firstWhere('id', $selected);
    }

    protected function idPrefix(): string
    {
        return 'schcmd-';
    }
}
