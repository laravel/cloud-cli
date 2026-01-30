<?php

namespace App\Resolvers;

use App\Dto\BackgroundProcess;
use App\Resolvers\Concerns\HasAnApplication;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class BackgroundProcessResolver extends Resolver
{
    use HasAnApplication;

    public function from(?string $idOrName = null): ?BackgroundProcess
    {
        $backgroundProcess = ($idOrName ? $this->fromIdentifier($idOrName) : null)
            ?? $this->fromInput();

        if (! $backgroundProcess) {
            $this->failAndExit('Unable to resolve background process: '.($idOrName ?? 'Provide a valid background process ID as an argument.'));
        }

        $this->displayResolved('Background Process', $backgroundProcess->command, $backgroundProcess->id);

        return $backgroundProcess;
    }

    public function fromIdentifier(string $identifier): ?BackgroundProcess
    {
        return $this->resolveFromIdentifier(
            $identifier,
            fn () => spin(
                fn () => $this->client->backgroundProcesses()->get($identifier),
                'Fetching background process...',
            ),
        );
    }

    public function fromInput(): ?BackgroundProcess
    {
        $instance = $this->resolvers()
            ->instance()
            ->withApplication($this->application())
            ->resolve();

        $backgroundProcesses = $this->client->backgroundProcesses()->list($instance->id)->collect();

        if ($backgroundProcesses->isEmpty()) {
            $this->failAndExit('No background processes found for instance '.$instance->name);
        }

        if ($backgroundProcesses->hasSole()) {
            return $backgroundProcesses->first();
        }

        $this->ensureInteractive('Please provide a background process ID.');

        $selected = select(
            label: 'Background Process',
            options: $backgroundProcesses->mapWithKeys(fn ($backgroundProcess) => [
                $backgroundProcess->id => $backgroundProcess->command,
            ])->toArray(),
        );

        // No need to display the resolved instance name, it will be displayed from the select above
        $this->displayResolved = false;

        return $backgroundProcesses->firstWhere('id', $selected);
    }

    protected function idPrefix(): string
    {
        return 'process-';
    }
}
