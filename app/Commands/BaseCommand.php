<?php

namespace App\Commands;

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
    use HasAClient;
    use Validates;

    protected Form $form;

    protected ?Resolvers $resolvers;

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('hide-secrets', null, InputOption::VALUE_NONE, 'Redact environment variable values in output');
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
        return $this->resolvers ??= app(Resolvers::class, ['client' => $this->client, 'isInteractive' => $this->isInteractive()]);
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
            $this->line(json_encode(['message' => $message]));
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

        if (is_string($data)) {
            $json = json_encode(['message' => $data]);
        } elseif ($data instanceof Jsonable) {
            $json = $data->toJson();
        } else {
            $json = json_encode($data);
        }

        if ($this->shouldHideSecrets()) {
            $decoded = json_decode($json, true);
            $decoded = $this->redactSecrets($decoded);
            $json = json_encode($decoded);
        }

        $this->line($json);

        throw new CommandExitException(self::SUCCESS);
    }

    protected function shouldHideSecrets(): bool
    {
        return $this->hasOption('hide-secrets') && $this->option('hide-secrets');
    }

    /**
     * Recursively redact environment variable values in the data structure.
     *
     * Looks for arrays containing 'key' and 'value' keys (the shape used by
     * environmentVariables in the JSON:API responses) and replaces the value
     * with a redacted placeholder.
     */
    protected function redactSecrets(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        // If this array has exactly 'key' and 'value' string entries, redact the value.
        if (array_key_exists('key', $data) && array_key_exists('value', $data) && is_string($data['key'])) {
            $data['value'] = '********';

            return $data;
        }

        foreach ($data as $k => $v) {
            $data[$k] = $this->redactSecrets($v);
        }

        return $data;
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

        // TODO: When would we ever get here?
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
