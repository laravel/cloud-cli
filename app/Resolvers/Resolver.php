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

    protected bool $nullable = false;

    public function __construct(
        protected Connector $client,
        protected LocalConfig $localConfig,
        protected bool $isInteractive,
    ) {
        //
    }

    public function nullable(): static
    {
        $this->nullable = true;

        return $this;
    }

    abstract protected function idPrefix(): string|callable;

    protected function resolveFromIdentifier(string $identifier, callable $ifIdCallback, ?callable $ifNotIdCallback = null): mixed
    {
        $ifNotIdCallback = $ifNotIdCallback ?? fn () => null;

        $idPrefix = $this->idPrefix();

        if (is_string($idPrefix) && ! str_starts_with($identifier, $idPrefix)) {
            return $ifNotIdCallback();
        }

        if (is_callable($idPrefix) && ! $idPrefix($identifier)) {
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
            fwrite(STDERR, json_encode(['error' => true, 'message' => $message]).PHP_EOL);

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

    protected function ensureInteractive(string $message, array $data = []): bool
    {
        if (! $this->isInteractive) {
            if ($this->nullable) {
                return false;
            }

            fwrite(STDERR, json_encode(array_merge(['error' => true, 'message' => $message], $data)).PHP_EOL);

            throw new CommandExitException(Command::FAILURE);
        }

        return true;
    }

    protected function displayResolved(string $label, string $answer, ?string $info = null): void
    {
        if ($this->displayResolved) {
            answered(label: $label, answer: $answer, info: $info);
        }
    }
}
