<?php

namespace App\Commands;

use App\Client\Requests\CreateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use App\Enums\InstanceScalingType;
use App\Enums\InstanceType;
use Illuminate\Support\Composer;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class ManagedQueueCreate extends BaseCommand
{
    protected ?string $jsonDataClass = EnvironmentInstance::class;

    protected $signature = 'managed-queue:create
                            {environment? : The environment ID or name}
                            {--name= : Queue name}
                            {--size= : Instance size}
                            {--max-workers= : Maximum number of workers}
                            {--visibility-timeout= : Visibility timeout in seconds (0-43200). Needs the advanced managed queues entitlement; left out, Laravel Cloud chooses it}
                            {--polling-interval= : Polling interval in seconds (0-60), previous-generation sizes only}
                            {--shutdown-timeout= : Shutdown timeout in seconds. Needs the advanced managed queues entitlement; left out, Laravel Cloud chooses it}
                            {--fifo : Create a FIFO queue; Laravel Cloud appends .fifo to the name}';

    protected $description = 'Create a new managed queue';

    protected $aliases = ['queue:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Managed Queue');

        $composer = app(Composer::class)->setWorkingPath(getcwd());

        if (! $composer->hasPackage('aws/aws-sdk-php')) {
            warning('The aws/aws-sdk-php package is required to create a managed queue.');

            if (confirm('Would you like to install it now?')) {
                spin(
                    fn () => $composer->requirePackages(['aws/aws-sdk-php']),
                    'Installing aws/aws-sdk-php...',
                );
            }
        }

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        // Asked here, outside the retry loop, because a bare flag reads false when omitted and the form would treat that as answered.
        $fifo = $this->option('fifo') || ($this->isInteractive() && confirm(
            label: 'Create a FIFO queue?',
            default: false,
            hint: 'Processes jobs in order, exactly once',
        ));

        $instance = $this->loopUntilValid(fn () => $this->createManagedQueue($environment->id, $fifo));

        $this->outputJsonIfWanted($instance);

        success("Managed queue created: {$instance->name}");
    }

    protected function createManagedQueue(string $environmentId, bool $fifo)
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Queue name',
                    hint: $fifo
                        ? 'Without the .fifo suffix, which Laravel Cloud appends'
                        : 'Must match the queue name your application dispatches to',
                    default: $value ?? 'default',
                    required: true,
                ),
            ),
        );

        [$current, $previous] = collect($this->client->instances()->sizes()->all())
            ->filter(fn ($size) => str_starts_with($size->name, 'mq'))
            ->partition(fn ($size) => $this->isCurrentGeneration($size->name));

        $sizes = $current->merge($previous);

        $this->form()->prompt(
            'size',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => select(
                    label: 'Size',
                    options: $sizes
                        ->mapWithKeys(fn ($size) => [$size->name => $size->description])
                        ->toArray(),
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'max_replicas',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn ($value) => (int) number(
                        label: 'Maximum workers',
                        default: $value ?? '3',
                        min: 1,
                    ),
                )
                ->nonInteractively(fn () => 3),
            'max-workers',
        );

        $this->promptForQueueTimeout(
            'visibility_timeout',
            'visibility-timeout',
            label: 'Visibility timeout (seconds)',
            hint: 'How long an in-flight job is hidden from other workers (0-43200). Leave empty to let Laravel Cloud choose; a custom value needs the advanced managed queues entitlement.',
            min: 0,
            max: 43200,
        );

        $size = $this->form()->get('size');

        // A warning rather than a refusal: the organization's generation is not the caller's choice.
        if ($this->isCurrentGeneration($size) && $this->option('polling-interval') !== null) {
            $this->outputWarning("Laravel Cloud ignores --polling-interval for {$size}, which is a current-generation size.");
        }

        if (! $this->isCurrentGeneration($size)) {
            $this->form()->prompt(
                'polling_interval',
                fn ($resolver) => $resolver
                    ->fromInput(
                        fn ($value) => (int) number(
                            label: 'Polling interval (seconds)',
                            hint: 'How often to check queue pressure for scaling decisions (0-60)',
                            default: $value ?? '5',
                            min: 0,
                            max: 60,
                        ),
                    )
                    ->nonInteractively(fn () => 5),
                'polling-interval',
            );
        }

        $this->promptForQueueTimeout(
            'shutdown_timeout',
            'shutdown-timeout',
            label: 'Shutdown timeout (seconds)',
            hint: 'Grace period for workers to finish their current job before terminating. Leave empty to let Laravel Cloud choose; a custom value needs the advanced managed queues entitlement.',
            min: 1,
        );

        $this->form()->prompt(
            'config.backoff',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Backoff',
                    default: $value ?? 0,
                    min: 0,
                    required: true,
                    hint: 'Number of seconds to wait before retrying a failed job.',
                ))
                ->nonInteractively(fn () => 0)
                ->shouldPromptOnce(),
            'backoff',
        );

        $this->form()->prompt(
            'config.tries',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Tries',
                    default: $value ?? 1,
                    min: 1,
                    required: true,
                    hint: 'Number of times a job should be attempted',
                ))
                ->nonInteractively(fn () => 1)
                ->shouldPromptOnce(),
            'tries',
        );

        $this->form()->prompt(
            'config.timeout',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Timeout',
                    default: $value ?? 60,
                    min: 0,
                    required: true,
                    hint: 'Number of seconds a job can run before timing out',
                ))
                ->nonInteractively(fn () => 60)
                ->shouldPromptOnce(),
            'timeout',
        );

        $queueName = $this->form()->get('name');

        return spin(
            fn () => $this->client->instances()->create(
                new CreateInstanceRequestData(
                    environmentId: $environmentId,
                    name: $queueName,
                    type: InstanceType::MANAGED_QUEUE,
                    size: $size,
                    scalingType: InstanceScalingType::CUSTOM,
                    minReplicas: 1,
                    maxReplicas: $this->form()->integer('max_replicas'),
                    visibilityTimeout: $this->queueTimeout('visibility_timeout'),
                    pollingInterval: $this->isCurrentGeneration($size) ? null : $this->form()->integer('polling_interval'),
                    shutdownTimeout: $this->queueTimeout('shutdown_timeout'),
                    backgroundProcesses: [
                        [
                            'type' => 'worker',
                            'processes' => 1,
                            'config' => [
                                'connection' => 'cloud',
                                'queue' => $queueName,
                                'backoff' => $this->form()->integer('config.backoff'),
                                'tries' => $this->form()->integer('config.tries'),
                                'timeout' => $this->form()->integer('config.timeout'),
                            ],
                        ],
                    ],
                    // Left out rather than false when omitted, so standard queues send the same payload as before.
                    fifo: $fifo ? true : null,
                ),
            ),
            'Creating managed queue...',
        );
    }

    protected function isCurrentGeneration(?string $size): bool
    {
        return str_starts_with((string) $size, 'mq.');
    }

    /**
     * Laravel Cloud applies a custom queue timeout only for organizations with the advanced managed
     * queues entitlement, and chooses its own otherwise. So a timeout is sent only when one was given,
     * never as a default: not for a missing option, and not for an empty answer.
     */
    protected function promptForQueueTimeout(string $key, string $option, string $label, string $hint, int $min, ?int $max = null): void
    {
        if (! $this->isInteractive() && $this->option($option) === null) {
            return;
        }

        $this->form()->prompt(
            $key,
            // An empty answer stays '' rather than null: the form asks again for null fields when the
            // API rejects another one, and the user already chose to leave this one to Laravel Cloud.
            fn ($resolver) => $resolver->fromInput(fn ($value) => number(
                label: $label,
                default: (string) ($value ?? ''),
                hint: $hint,
                min: $min,
                max: $max,
            )),
            $option,
        );
    }

    /**
     * The timeout to send: none when it was left out or answered empty.
     */
    protected function queueTimeout(string $key): ?int
    {
        $value = $this->form()->get($key);

        return $value === null || $value === '' ? null : (int) $value;
    }
}
