<?php

namespace App\Concerns;

use App\Dto\ValidationErrors;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

use function Laravel\Prompts\error;

/**
 * @template TReturn
 */
trait Validates
{
    /**
     * @param  callable(ValidationErrors): TReturn  $callback
     * @return TReturn
     */
    protected function loopUntilValid(callable $callback, int $maxAttempts = 10, bool|callable $suppressOutput = false): mixed
    {
        $result = null;
        $errors = new ValidationErrors;
        $attempts = 0;

        while (! $result) {
            if ($attempts >= $maxAttempts) {
                throw new RuntimeException('Maximum attempts reached');
            }

            $attempts++;

            try {
                $result = $callback($errors);

                return $result;
            } catch (RequestException $e) {
                $errors->clear();

                if ($e->response?->status() === 422) {
                    $responseErrors = $e->response->json()['errors'] ?? [];

                    if (count($responseErrors) > 0) {
                        foreach ($responseErrors as $field => $messages) {
                            $this->displayValidationError(ucwords($field).': '.implode(', ', $messages), $suppressOutput);

                            $errors->add($field, implode(', ', $messages));
                        }
                    } else {
                        $message = $e->response->json()['message'] ?? 'Unknown validation error';

                        $this->displayValidationError($message, $suppressOutput);
                    }
                } else {
                    $this->displayValidationError($e->getMessage(), $suppressOutput);
                }
            }
        }

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
}
