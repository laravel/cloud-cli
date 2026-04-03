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
use Illuminate\Support\Arr;
use Laravel\Prompts\Concerns\Colors;
use Laravel\Prompts\Prompt;
use LaravelZero\Framework\Commands\Command;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
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

    /** @var class-string<Data>|null */
    protected ?string $jsonDataClass = null;

    protected bool $jsonDataIsCollection = false;

    protected function configure(): void
    {
        parent::configure();

        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
        $this->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Filter JSON output to specific fields (comma-separated, supports dot notation for nested fields)');

        if ($this->jsonDataClass) {
            $fields = $this->describeJsonFields($this->jsonDataClass);
            $prefix = $this->jsonDataIsCollection ? 'Each item contains: ' : '';
            $this->setHelp("Available JSON fields:\n  {$prefix}{$fields}");
        }
    }

    protected function describeJsonFields(string $dataClass, string $prefix = '', int $depth = 0): string
    {
        $reflection = new ReflectionClass($dataClass);
        $fields = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
            $name = $prefix.$param->getName();
            $type = $param->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($type instanceof ReflectionNamedType && $type->getName() === 'array' && $depth === 0) {
                    $collectionAttr = $param->getAttributes(DataCollectionOf::class)[0] ?? null;

                    if ($collectionAttr) {
                        $nestedClass = $collectionAttr->getArguments()[0];
                        $fields[] = $name.'[]';
                        $fields[] = $this->describeJsonFields($nestedClass, $param->getName().'.', 1);

                        continue;
                    }
                }

                $fields[] = $name;

                continue;
            }

            $typeName = $type->getName();

            if ($depth === 0 && is_subclass_of($typeName, Data::class)) {
                $fields[] = $name;
                $fields[] = $this->describeJsonFields($typeName, $param->getName().'.', 1);

                continue;
            }

            $fields[] = $name;
        }

        return implode(', ', $fields);
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
            fwrite(STDERR, json_encode(['error' => true, 'message' => $message]).PHP_EOL);
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

    protected function writeJsonIfWanted(mixed $data): void
    {
        if (! $this->wantsJson()) {
            return;
        }

        $this->line($this->toJson($data));
    }

    protected function outputJsonIfWanted(mixed $data): void
    {
        if (! $this->wantsJson()) {
            return;
        }

        $json = $this->toJson($data);

        if (! is_string($data) && $fields = $this->option('fields')) {
            $json = json_encode($this->filterByFields(json_decode($json, true), $fields));
        }

        $this->line($json);

        throw new CommandExitException(self::SUCCESS);
    }

    protected function toJson(mixed $data): string
    {
        if (is_string($data)) {
            return json_encode(['message' => $data]);
        }

        if ($data instanceof Jsonable) {
            return $data->toJson();
        }

        return json_encode($data);
    }

    protected function filterByFields(array $data, string $fields): array
    {
        $fieldList = array_map('trim', explode(',', $fields));

        if (array_is_list($data)) {
            return array_map(fn ($item) => $this->pickFields($item, $fieldList), $data);
        }

        return $this->pickFields($data, $fieldList);
    }

    protected function pickFields(array $item, array $fields): array
    {
        $dotted = Arr::dot($item);

        $filtered = collect($dotted)
            ->filter(fn ($value, $dottedKey) => $this->matchesRequestedField($dottedKey, $fields))
            ->all();

        return Arr::undot($filtered);
    }

    protected function matchesRequestedField(string $dottedKey, array $fields): bool
    {
        $normalized = collect(explode('.', $dottedKey))
            ->reject(fn ($segment) => is_numeric($segment))
            ->implode('.');

        foreach ($fields as $field) {
            if ($normalized === $field || str_starts_with($normalized, $field.'.')) {
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

    protected function confirmDestructive(string $message): void
    {
        if ($this->option('force')) {
            return;
        }

        if (! $this->isInteractive()) {
            $this->failAndExit('Destructive operation requires --force flag in non-interactive mode.');
        }

        if (! confirm($message, default: false)) {
            error('Cancelled');

            throw new CommandExitException(self::FAILURE);
        }
    }
}
