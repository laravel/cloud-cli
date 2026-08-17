<?php

namespace App\Concerns;

use App\Commands\BaseCommand;
use App\Dto\ValidationErrors;
use App\Exceptions\CommandExitException;
use RuntimeException;
use Saloon\Exceptions\Request\RequestException;

use function Laravel\Prompts\error;

/**
 * @template TReturn
 */
trait Validates
{
    protected ?ValidationErrors $errors = null;

    /**
     * @param  callable(ValidationErrors): TReturn  $callback
     * @return TReturn
     */
    protected function loopUntilValid(callable $callback, int $maxAttempts = 20, bool|callable $suppressOutput = false, ?callable $handleNonInteractiveErrors = null, ?callable $shouldRetry = null): mixed
    {
        $this->errors ??= new ValidationErrors;
        $attempts = 0;
        $previousFailure = null;
        $this->form()->errors($this->errors);

        while (true) { // @phpstan-ignore while.alwaysTrue
            if ($attempts >= $maxAttempts) {
                throw new RuntimeException('Maximum attempts reached');
            }

            $this->breakValidationLoopIfNonInteractive($handleNonInteractiveErrors);

            $attempts++;

            try {
                $result = $callback($this->errors);

                // Errors from an earlier attempt would otherwise follow us into the next loop.
                $this->errors->clear();

                return $result;
            } catch (RequestException $e) {
                $this->errors->clear();

                if ($e->getResponse()->status() === 422) {
                    $responseErrors = $e->getResponse()->json('errors', []);

                    if (count($responseErrors) > 0) {
                        foreach ($responseErrors as $field => $messages) {
                            $this->displayValidationError(ucwords($field).': '.implode(', ', $messages), $suppressOutput);

                            $this->errors->add($field, implode(', ', $messages));
                        }

                        $failure = (string) json_encode($this->errors->all());
                    } else {
                        $message = $e->getResponse()->json('message', 'Unknown validation error');

                        $this->displayValidationError($message, $suppressOutput);

                        $failure = $message;
                    }
                } else {
                    $this->displayValidationError($e->getMessage(), $suppressOutput);

                    $failure = $e->getMessage();
                }

                $this->breakValidationLoopIfStuck($failure, $previousFailure, $shouldRetry, $suppressOutput);

                $previousFailure = $failure;
            }
        }
    }

    /**
     * Stop retrying when the same failure comes back and there is nothing for the user to change.
     *
     * Errors the API does not tie to a field (a plan restriction, say) have no prompt to send the
     * user back to, so retrying replays the same request until the attempt limit runs out.
     */
    protected function breakValidationLoopIfStuck(string $failure, ?string $previousFailure, ?callable $shouldRetry, bool|callable $suppressOutput): void
    {
        if (! $this->isInteractive()) {
            return;
        }

        if ($shouldRetry !== null && $shouldRetry($this->errors)) {
            return;
        }

        if ($this->form()->canPromptForAnyOf($this->errors)) {
            return;
        }

        if ($failure !== $previousFailure) {
            return;
        }

        $this->displayValidationError('Fix the error above, then run again.', $suppressOutput);

        throw new CommandExitException(BaseCommand::FAILURE);
    }

    protected function displayValidationError(string $message, bool|callable $suppressOutput): void
    {
        if (is_callable($suppressOutput)) {
            if (! $suppressOutput($message)) {
                error($message);
            }

            return;
        }

        if ($suppressOutput) {
            return;
        }

        error($message);
    }

    protected function breakValidationLoopIfNonInteractive(?callable $handleNonInteractiveErrors = null): void
    {
        if ($this->errors->isEmpty() || $this->isInteractive()) {
            return;
        }

        if ($handleNonInteractiveErrors && $handleNonInteractiveErrors($this->errors)) {
            return;
        }

        if (! $this->wantsJson()) {
            throw new RuntimeException($this->errors);
        }

        $values = collect($this->errors->all())
            ->keys()
            ->mapWithKeys(fn ($field) => [$field => $this->form()->get($field)])
            ->filter()
            ->all();

        fwrite(STDERR, $this->errors->toJson($values).PHP_EOL);

        throw new CommandExitException(BaseCommand::FAILURE);
    }
}
