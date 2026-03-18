<?php

namespace App\Resolvers;

use App\Client\Connector;
use App\Concerns\FormatsErrors;
use App\Exceptions\CommandExitException;
use App\LocalConfig;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\error;

abstract class Resolver
{
    use FormatsErrors;

    protected bool $displayResolved = true;

    public function __construct(
        protected Connector $client,
        protected LocalConfig $localConfig,
        protected bool $isInteractive,
        protected ?string $applicationFlag = null,
        protected ?string $environmentFlag = null,
    ) {
        //
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
            echo $this->formatErrorAsJson($message).PHP_EOL;

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
            'applicationFlag' => $this->applicationFlag,
            'environmentFlag' => $this->environmentFlag,
        ]);
    }

    protected function ensureInteractive(string $message): void
    {
        if (! $this->isInteractive) {
            echo $this->formatErrorAsJson($message).PHP_EOL;

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
