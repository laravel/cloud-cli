<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\Validates;
use App\Resolvers\Resolvers;
use App\Support\ValueResolver;
use Illuminate\Contracts\Support\Jsonable;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\error;

abstract class BaseCommand extends Command
{
    use Colors;
    use HasAClient;
    use Validates;

    /**
     * @var array<string, ValueResolver>
     */
    protected array $paramCollectors = [];

    protected ?Resolvers $resolvers;

    /**
     * @param  callable(ValueResolver): ValueResolver  $resolver
     */
    protected function addParam(string $name, callable $resolver): void
    {
        $existing = $this->paramCollectors[$name] ?? $this->resolve($name);

        $this->paramCollectors[$name] = $resolver($existing)->errors($this->errors);
        $this->paramCollectors[$name]->retrieve();
    }

    protected function resolvers(): Resolvers
    {
        return $this->resolvers ??= app(Resolvers::class, ['client' => $this->client, 'isInteractive' => $this->isInteractive()]);
    }

    protected function failAndExit(string $message): void
    {
        error($message);

        exit(self::FAILURE);
    }

    protected function getParam(string $name, mixed $default = null): ?string
    {
        if (! array_key_exists($name, $this->paramCollectors)) {
            return $default;
        }

        return $this->paramCollectors[$name]?->value();
    }

    protected function getParams(): array
    {
        return collect($this->paramCollectors)->mapWithKeys(
            fn (ValueResolver $resolver) => [
                $resolver->key() => $resolver->value(),
            ],
        )->toArray();
    }

    protected function ensureInteractive(string $message): void
    {
        if (! $this->isInteractive()) {
            throw new RuntimeException($message);
        }
    }

    protected function reportChange(string $field, string $oldValue, string $newValue): void
    {
        dataList([
            $field => $this->dim($this->yellow($oldValue).' →').' '.$this->green($newValue),
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

    protected function isNonInteractiveEnvironment(): bool
    {
        $envs = [
            'CI',
            'CURSOR',
            'GITHUB_ACTIONS',
            'GITLAB_CI',
            'JENKINS_URL',
            'CIRCLECI',
            'TRAVIS',
            'AGENT_MODE',
        ];

        foreach ($envs as $env) {
            if (! empty(getenv($env))) {
                return true;
            }
        }

        return false;
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
        if ($this->wantsJson()) {
            if (is_string($data)) {
                $this->line(json_encode(['message' => $data]));
            } elseif ($data instanceof Jsonable) {
                $this->line($data->toJson());
            } else {
                $this->line(json_encode($data));
            }

            exit(self::SUCCESS);
        }
    }

    protected function resolve(string $argument, ?string $value = null): ValueResolver
    {
        return new ValueResolver(
            $argument,
            $this->isInteractive(),
            $value ?? match (true) {
                $this->hasOption($argument) => $this->option($argument),
                $this->hasArgument($argument) => $this->argument($argument),
                default => null,
            },
            $this->hasOption($argument) ? 'option' : 'argument',
        );
    }
}
