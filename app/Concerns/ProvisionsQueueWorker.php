<?php

namespace App\Concerns;

use App\Client\Requests\CreateBackgroundProcessRequestData;
use App\Client\Requests\CreateInstanceRequestData;
use App\Dto\Environment;
use App\Enums\InstanceScalingType;
use App\Enums\InstanceType;
use Dotenv\Dotenv;
use Illuminate\Support\Composer;
use InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Throwable;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

trait ProvisionsQueueWorker
{
    protected const QUEUE_TYPES = ['worker', 'managed', 'horizon'];

    protected ?string $requestedQueueType = null;

    abstract protected function projectPath(): string;

    abstract protected function composer(): ?Composer;

    /**
     * Framework and package code is full of ShouldQueue classes, so only the app's own code says anything about it.
     */
    protected function appDispatchesQueuedJobs(): bool
    {
        $appPath = $this->projectPath().'/app';

        if (! is_dir($appPath)) {
            return false;
        }

        try {
            $files = Finder::create()->files()->in($appPath)->name('*.php')->contains('ShouldQueue');

            return $files->hasResults();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Each Cloud option assumes a different queue driver, so follow the one the app is already set up for.
     */
    protected function recommendedQueueType(): string
    {
        $composer = $this->composer();

        if ($composer?->hasPackage('laravel/horizon')) {
            return 'horizon';
        }

        if ($this->localQueueConnection() === 'sqs' || $composer?->hasPackage('aws/aws-sdk-php')) {
            return 'managed';
        }

        return 'worker';
    }

    protected function localQueueConnection(): ?string
    {
        foreach (['.env', '.env.example'] as $file) {
            $path = $this->projectPath().'/'.$file;

            if (! file_exists($path)) {
                continue;
            }

            try {
                $connection = Dotenv::parse(file_get_contents($path))['QUEUE_CONNECTION'] ?? null;
            } catch (Throwable) {
                continue;
            }

            if ($connection !== null) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * Runs before anything is created, so a typo does not leave a half-shipped application behind.
     */
    protected function resolveRequestedQueueType(): void
    {
        $type = $this->option('queue');

        if ($type === null) {
            return;
        }

        if (! in_array($type, self::QUEUE_TYPES, true)) {
            $this->outputErrorOrThrow('Invalid --queue value `'.$type.'`. Use one of: '.implode(', ', self::QUEUE_TYPES).'.');

            return;
        }

        $this->requestedQueueType = $type;
    }

    protected function promptForQueueType(): string
    {
        $recommended = $this->requestedQueueType ?? $this->recommendedQueueType();

        $options = [
            'worker' => 'Background worker on the app instance (database driver)',
            'managed' => 'Managed queue (scales its own workers, requires aws/aws-sdk-php)',
        ];

        if ($this->composer()?->hasPackage('laravel/horizon')) {
            $options['horizon'] = 'Horizon on the app instance (requires Redis)';
        }

        $options[$recommended] .= ' (recommended)';

        return select(
            label: 'Queue worker type',
            options: $options,
            default: $recommended,
        );
    }

    protected function provisionQueue(string $type, Environment $environment): void
    {
        match ($type) {
            'worker' => $this->createBackgroundProcess($environment, new CreateBackgroundProcessRequestData(
                instanceId: $environment->instances[0],
                type: 'worker',
                processes: 1,
                config: [
                    'connection' => 'database',
                    'queue' => 'default',
                    'tries' => 1,
                    'backoff' => 30,
                    'sleep' => 10,
                    'rest' => 0,
                    'timeout' => 60,
                    'force' => false,
                ],
            )),
            'horizon' => $this->createHorizonProcess($environment),
            'managed' => $this->createManagedQueue($environment),
            default => throw new InvalidArgumentException("Unknown queue type [{$type}]."),
        };
    }

    protected function createHorizonProcess(Environment $environment): void
    {
        $this->outputWarning('Horizon needs Redis. Attach a cache to this environment before jobs will run.');

        $this->createBackgroundProcess($environment, new CreateBackgroundProcessRequestData(
            instanceId: $environment->instances[0],
            type: 'custom',
            processes: 1,
            command: 'php artisan horizon',
        ));
    }

    protected function createBackgroundProcess(Environment $environment, CreateBackgroundProcessRequestData $data): void
    {
        $this->loopUntilValid(
            fn () => spin(
                fn () => $this->client->backgroundProcesses()->create($data),
                'Creating queue worker...',
            ),
        );
    }

    protected function createManagedQueue(Environment $environment): void
    {
        // Ship deploys straight from the remote, so installing the package locally would not reach this deploy.
        if (! $this->composer()?->hasPackage('aws/aws-sdk-php')) {
            $this->outputWarning('Managed queues need aws/aws-sdk-php. Require it, then commit and push before jobs will run.');
        }

        $size = $this->managedQueueSize();

        if ($size === null) {
            return;
        }

        $this->loopUntilValid(
            fn () => spin(
                fn () => $this->client->instances()->create(new CreateInstanceRequestData(
                    environmentId: $environment->id,
                    name: 'default',
                    type: InstanceType::MANAGED_QUEUE,
                    size: $size,
                    scalingType: InstanceScalingType::CUSTOM,
                    minReplicas: 1,
                    maxReplicas: 3,
                    visibilityTimeout: 60,
                    pollingInterval: str_starts_with($size, 'mq.') ? null : 5,
                    shutdownTimeout: 90,
                    backgroundProcesses: [
                        [
                            'type' => 'worker',
                            'processes' => 1,
                            'config' => [
                                'connection' => 'cloud',
                                'queue' => 'default',
                                'backoff' => 0,
                                'tries' => 1,
                                'timeout' => 60,
                            ],
                        ],
                    ],
                )),
                'Creating managed queue...',
            ),
        );
    }

    protected function managedQueueSize(): ?string
    {
        // Current-generation sizes use dots, and Cloud ignores the polling interval for them.
        [$current, $previous] = collect($this->client->instances()->sizes()->all())
            ->filter(fn ($size) => str_starts_with($size->name, 'mq'))
            ->partition(fn ($size) => str_starts_with($size->name, 'mq.'));

        $sizes = $current->merge($previous);

        if ($sizes->isEmpty()) {
            $this->outputErrorOrThrow('Laravel Cloud did not offer any managed queue sizes.');

            return null;
        }

        if (! $this->isInteractive()) {
            return $sizes->first()->name;
        }

        return select(
            label: 'Managed queue size',
            options: $sizes->mapWithKeys(fn ($size) => [$size->name => $size->description])->toArray(),
            default: $sizes->first()->name,
        );
    }
}
