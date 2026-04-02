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
    protected function loopUntilValid(callable $callback, int $maxAttempts = 20, bool|callable $suppressOutput = false, ?callable $handleNonInteractiveErrors = null): mixed
    {
        $this->errors ??= new ValidationErrors;
        $attempts = 0;
        $this->form()->errors($this->errors);

        while (true) { // @phpstan-ignore while.alwaysTrue
            if ($attempts >= $maxAttempts) {
                throw new RuntimeException('Maximum attempts reached');
            }

            $this->breakValidationLoopIfNonInteractive($handleNonInteractiveErrors);

            $attempts++;

            try {
                return $callback($this->errors);
            } catch (RequestException $e) {
                $this->errors->clear();

                if ($e->getResponse()->status() === 422) {
                    $responseErrors = $e->getResponse()->json('errors', []);

                    if (count($responseErrors) > 0) {
                        foreach ($responseErrors as $field => $messages) {
                            $this->displayValidationError(ucwords($field).': '.implode(', ', $messages), $suppressOutput);

                            $this->errors->add($field, implode(', ', $messages));
                        }
                    } else {
                        $message = $e->getResponse()->json('message', 'Unknown validation error');

                        $this->displayValidationError($message, $suppressOutput);
                    }
                } else {
                    $this->displayValidationError($e->getMessage(), $suppressOutput);
                }
            }
        }
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

        fwrite(STDERR, $this->errors->toJson().PHP_EOL);

        throw new CommandExitException(BaseCommand::FAILURE);
    }
}
