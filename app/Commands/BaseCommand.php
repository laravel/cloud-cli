<?php

namespace App\Commands;

use App\Concerns\FormatsErrors;
use App\Concerns\HasAClient;
use App\Concerns\Validates;
use App\Exceptions\CommandExitException;
use App\Prompts\Renderer;
use App\Prompts\SuppressedOutput;
use App\Resolvers\Resolvers;
use App\Support\DetectsNonInteractiveEnvironments;
use App\Support\Form;
use App\Support\ValueResolver;
use Illuminate\Contracts\Support\Jsonable;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;

abstract class BaseCommand extends Command
{
    use Colors;
    use DetectsNonInteractiveEnvironments;
    use FormatsErrors;
    use HasAClient;
    use Validates;

    /**
     * Sensitive field names that should be redacted when --hide-secrets is used.
     */
    protected const SENSITIVE_FIELD_NAMES = [
        'password',
        'secret',
        'api_key',
        'apiKey',
        'access_key',
        'accessKey',
        'secret_key',
        'secretKey',
        'secret_access_key',
        'secretAccessKey',
        'token',
        'private_key',
        'privateKey',
    ];

    protected const REDACTED_VALUE = '********';

    protected Form $form;

    protected ?Resolvers $resolvers;

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'token',
            null,
            InputOption::VALUE_REQUIRED,
            'Laravel Cloud API token (overrides stored tokens and LARAVEL_CLOUD_API_TOKEN env var)',
        );

        $this->addOption('application', null, InputOption::VALUE_REQUIRED, 'The application ID or name');
        $this->addOption('environment', null, InputOption::VALUE_REQUIRED, 'The environment ID or name');

        $this->getDefinition()->addOption(
            new InputOption('hide-secrets', null, InputOption::VALUE_NONE, 'Redact sensitive values in output'),
        );
    }

    protected function form(): Form
    {
        return $this->form ??= (new Form)
            ->options($this->options())
            ->arguments($this->arguments())
            ->isInteractive($this->isInteractive());
    }

    protected function resolvers(): Resolvers
    {
        return $this->resolvers ??= app(Resolvers::class, [
            'client' => $this->client,
            'isInteractive' => $this->isInteractive(),
            'applicationFlag' => $this->option('application'),
            'environmentFlag' => $this->option('environment'),
        ]);
    }

    protected function runningAsSubcommand(): bool
    {
        return $this->input instanceof ArrayInput;
    }

    protected function configurePrompts(InputInterface $input): void
    {
        parent::configurePrompts($input);

        if (Renderer::$suppressOutput) {
            Prompt::setOutput(new SuppressedOutput);
        }
    }

    protected function failAndExit(string $message): void
    {
        $this->outputError($message);

        throw new CommandExitException(self::FAILURE);
    }

    /**
     * Output an error in the correct format (JSON when wantsJson(), else stderr).
     */
    protected function outputError(string $message): void
    {
        if ($this->wantsJson()) {
            $this->line($this->formatErrorAsJson($message));
        } else {
            error($message);
        }
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (CommandExitException $e) {
            return $e->getExitCode();
        } catch (RuntimeException $e) {
            if ($this->wantsJson()) {
                $this->outputError($e->getMessage());

                return self::FAILURE;
            }

            throw $e;
        }
    }

    protected function ensureInteractive(string $message): void
    {
        if (! $this->isInteractive()) {
            throw new RuntimeException($message);
        }
    }

    protected function reportChange(string $field, ?string $oldValue, ?string $newValue): void
    {
        dataList([
            $field => $this->dim($this->yellow($oldValue ?? '—').' →').' '.$this->green($newValue ?? '—'),
        ]);
    }

    protected function isInteractive(): bool
    {
        if ($this->option('no-interaction')) {
            return false;
        }

        if ($this->isNonInteractiveEnvironment()) {
            return false;
        }

        if (! stream_isatty(STDIN)) {
            return false;
        }

        if ($this->requestedJson()) {
            return false;
        }

        return true;
    }

    protected function outputErrorOrThrow(string $message): void
    {
        if ($this->isInteractive()) {
            error($message);
        } else {
            throw new RuntimeException($message);
        }
    }

    protected function requestedJson(): bool
    {
        return $this->hasOption('json') && $this->option('json');
    }

    protected function wantsJson(): bool
    {
        if ($this->requestedJson() || ! $this->isInteractive()) {
            return true;
        }

        return false;
    }

    protected function outputJsonIfWanted(mixed $data): void
    {
        if (! $this->wantsJson()) {
            return;
        }

        if ($this->shouldHideSecrets()) {
            $data = $this->redactSecrets($data);
        }

        if (is_string($data)) {
            $this->line(json_encode(['message' => $data]));
        } elseif ($data instanceof Jsonable) {
            $jsonData = json_decode($data->toJson(), true);

            if ($this->shouldHideSecrets() && is_array($jsonData)) {
                $jsonData = $this->redactSecrets($jsonData);
            }
            $this->line(json_encode($jsonData));
        } else {
            $this->line(json_encode($data));
        }

        throw new CommandExitException(self::SUCCESS);
    }

    /**
     * Determine whether secrets should be hidden in output.
     */
    protected function shouldHideSecrets(): bool
    {
        return $this->hasOption('hide-secrets') && $this->option('hide-secrets');
    }

    /**
     * Recursively redact sensitive values from the given data.
     *
     * Redacts:
     * - Arrays with 'key' + 'value' structure (environment variables) where key looks sensitive
     * - Any field whose name matches a known sensitive field name (password, secret, etc.)
     */
    protected function redactSecrets(mixed $data): mixed
    {
        if ($data instanceof Jsonable) {
            $decoded = json_decode($data->toJson(), true);

            return is_array($decoded) ? $this->redactSecrets($decoded) : $data;
        }

        if (! is_array($data)) {
            return $data;
        }

        // Handle environment variable style arrays: [{key: "DB_PASSWORD", value: "secret"}]
        if (isset($data['key'], $data['value']) && is_string($data['key'])) {
            if ($this->isSensitiveKeyName($data['key'])) {
                $data['value'] = self::REDACTED_VALUE;
            }

            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveFieldName($key) && is_string($value)) {
                $data[$key] = self::REDACTED_VALUE;
            } elseif (is_array($value) || $value instanceof Jsonable) {
                $data[$key] = $this->redactSecrets($value);
            }
        }

        return $data;
    }

    /**
     * Check if a field name is a known sensitive credential field.
     */
    protected function isSensitiveFieldName(string $name): bool
    {
        return in_array($name, self::SENSITIVE_FIELD_NAMES, true);
    }

    /**
     * Check if an environment variable key name looks sensitive.
     */
    protected function isSensitiveKeyName(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (['password', 'secret', 'api_key', 'token', 'private_key', 'access_key'] as $sensitive) {
            if (str_contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    protected function resolve(string $argument): ValueResolver
    {
        return new ValueResolver(
            $argument,
            $argument,
            $this->isInteractive(),
            match (true) {
                $this->hasOption($argument) => $this->option($argument),
                $this->hasArgument($argument) => $this->argument($argument),
                default => null,
            },
            $this->hasOption($argument) ? 'option' : 'argument',
        );
    }

    protected function runUpdate(callable $noninteractiveCallback, callable $interactiveCallback, ?string $resourceType = null): mixed
    {
        $resourceType ??= str(class_basename(get_called_class()))->replace('Update', '')->replaceMatches('/[A-Z]/', ' $0')->trim()->lower()->toString();

        if (! $this->isInteractive()) {
            if (! $this->form()->hasAnyValues()) {
                $this->outputErrorOrThrow('No fields to update. Provide at least one option.');

                throw new CommandExitException(self::FAILURE);
            }

            return $noninteractiveCallback();
        }

        if ($this->form()->isEmpty()) {
            return $this->loopUntilValid(function () use ($interactiveCallback, $noninteractiveCallback) {
                if ($this->errors->isEmpty()) {
                    return $interactiveCallback();
                }

                foreach ($this->errors->all() as $field => $message) {
                    $this->form()->prompt($field);
                }

                return $noninteractiveCallback();
            });
        }

        if (! $this->confirmUpdate($resourceType)) {
            error('Cancelled');

            throw new CommandExitException(self::FAILURE);
        }

        // Interactive mode with pre-filled values (CLI flags) and user confirmed
        return $noninteractiveCallback();
    }

    protected function confirmUpdate(string $resourceType): bool
    {
        if ($this->hasOption('force') && $this->option('force')) {
            return true;
        }

        return confirm('Update the '.$resourceType.'?');
    }
}
