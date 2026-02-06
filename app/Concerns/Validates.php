<?php

namespace App\Concerns;

use App\Commands\BaseCommand;
use App\Dto\ValidationErrors;
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
    protected function loopUntilValid(callable $callback, int $maxAttempts = 10, bool|callable $suppressOutput = false): mixed
    {
        $result = null;
        $this->errors ??= new ValidationErrors;
        $attempts = 0;

        while (! $result) {
            if ($attempts >= $maxAttempts) {
                throw new RuntimeException('Maximum attempts reached');
            }

            $this->breakValidationLoopIfNotInteractive();

            $attempts++;

            try {
                $result = $callback($this->errors);

                return $result;
            } catch (RequestException $e) {
                $this->errors->clear();

                if ($e->getResponse()?->status() === 422) {
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

        $this->errors->clear();
        $this->clearParams();

        return $result;
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

    protected function breakValidationLoopIfNotInteractive(): void
    {
        if ($this->errors->hasAny() && ! $this->isInteractive()) {
            if (! $this->wantsJson()) {
                throw new RuntimeException($this->errors);
            }

            $this->line($this->errors->toJson());

            exit(BaseCommand::FAILURE);
        }
    }
}
