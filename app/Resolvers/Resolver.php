<?php

namespace App\Resolvers;

use App\Client\Connector;
use App\Exceptions\CommandExitException;
use App\LocalConfig;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\error;

abstract class Resolver
{
    protected bool $displayResolved = true;

    public function __construct(
        protected Connector $client,
        protected LocalConfig $localConfig,
        protected bool $isInteractive,
    ) {
        //
    }

    abstract protected function idPrefix(): string;

    protected function resolveFromIdentifier(string $identifier, callable $ifIdCallback, ?callable $ifNotIdCallback = null): mixed
    {
        $ifNotIdCallback = $ifNotIdCallback ?? fn () => null;

        if (! str_starts_with($identifier, $this->idPrefix())) {
            return $ifNotIdCallback();
        }

        try {
            return $ifIdCallback();
        } catch (Throwable $e) {
            return $ifNotIdCallback();
        }
    }

    public function shouldDisplayResolved(bool $displayResolved = true): static
    {
        $this->displayResolved = $displayResolved;

        return $this;
    }

    protected function failAndExit(string $message): void
    {
        if (! $this->isInteractive) {
            echo json_encode(['message' => $message]).PHP_EOL;

            throw new CommandExitException(Command::FAILURE);
        }

        error($message);

        throw new CommandExitException(Command::FAILURE);
    }

    protected function resolvers(): Resolvers
    {
        return app(Resolvers::class, [
            'client' => $this->client,
            'localConfig' => $this->localConfig,
            'isInteractive' => $this->isInteractive,
        ]);
    }

    protected function ensureInteractive(string $message): void
    {
        if (! $this->isInteractive) {
            echo json_encode(['message' => $message]).PHP_EOL;

            throw new CommandExitException(Command::FAILURE);
        }
    }

    protected function displayResolved(string $label, string $answer, ?string $info = null): void
    {
        if ($this->displayResolved) {
            answered(label: $label, answer: $answer, info: $info);
        }
    }
}
