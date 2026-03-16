<?php

namespace App\Resolvers;

use App\Client\Connector;
use App\Exceptions\CommandExitException;
use App\LocalConfig;
use Illuminate\Console\Command;
use Saloon\Exceptions\Request\RequestException;
use Throwable;

use function Laravel\Prompts\error;

abstract class Resolver
{
    protected bool $displayResolved = true;

    protected ?string $lastResolutionError = null;

    public function __construct(
        protected Connector $client,
        protected LocalConfig $localConfig,
        protected bool $isInteractive,
    ) {
        //
    }

    abstract protected function idPrefix(): string|callable;

    protected function looksLikeId(string $identifier): bool
    {
        $idPrefix = $this->idPrefix();

        if (is_string($idPrefix)) {
            return str_starts_with($identifier, $idPrefix);
        }

        return (bool) $idPrefix($identifier);
    }

    protected function resolveFromIdentifier(string $identifier, callable $ifIdCallback, ?callable $ifNotIdCallback = null): mixed
    {
        $ifNotIdCallback = $ifNotIdCallback ?? fn () => null;

        if (! $this->looksLikeId($identifier)) {
            return $ifNotIdCallback();
        }

        try {
            return $ifIdCallback();
        } catch (RequestException $e) {
            $status = $e->getResponse()->status();

            if ($status === 404) {
                $this->lastResolutionError = "No resource found with ID '{$identifier}'.";

                return $ifNotIdCallback();
            }

            if ($status === 403) {
                $this->lastResolutionError = "You do not have permission to access '{$identifier}'.";

                throw $e;
            }

            $message = $e->getResponse()->json('message') ?? $e->getMessage();
            $this->lastResolutionError = "API error ({$status}) while fetching '{$identifier}': {$message}";

            throw $e;
        } catch (Throwable $e) {
            $this->lastResolutionError = $e->getMessage();

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
