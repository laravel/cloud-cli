<?php

namespace App\Resolvers;

use App\Dto\Application;
use App\Dto\Command;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class CommandResolver extends Resolver
{
    use HasAnApplication;

    public function resolve(): ?Command
    {
        return $this->from();
    }

    public function from(?string $idOrName = null): ?Command
    {
        $identifier = $idOrName ?? $this->localConfig->applicationId();

        $command = ($identifier ? $this->fromIdentifier($identifier) : null) ?? $this->fromInput();

        if (! $command) {
            $this->failAndExit('Unable to resolve command: '.($idOrName ?? 'Provide a valid command ID.').'. Run `cloud command:list --json` to see available commands.');
        }

        $this->displayResolved('Command', $command->command, $command->id);

        return $command;
    }

    public function fromIdentifier(string $identifier): ?Command
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->commands()->include('environment', 'deployment', 'initiator')->get($identifier),
                'Fetching command...',
            ),
        );
    }

    public function fromInput(): ?Command
    {
        $environment = $this->resolvers()
            ->environment()
            ->withApplication($this->application())
            ->resolve();
        $commands = $this->client->commands()->include('environment', 'deployment', 'initiator')->list($environment->id)->collect();

        if ($commands->hasSole()) {
            return $commands->first();
        }

        if ($commands->isEmpty()) {
            $this->failAndExit('No commands found for environment '.$environment->name);
        }

        $options = $commands->mapWithKeys(fn ($command) => [$command->id => $command->command])->toArray();

        $this->ensureInteractive('Multiple commands found. Provide a command ID.', ['options' => $options]);

        $selectedCommand = selectWithContext(
            label: 'Command',
            options: $options,
        );

        // No need to display the resolved application name, it will be displayed from the select above
        $this->displayResolved = false;

        return $commands->firstWhere('id', $selectedCommand);
    }

    protected function idPrefix(): string
    {
        return 'comm-';
    }
}
