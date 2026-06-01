<?php

namespace App\Commands;

use App\Client\Requests\CreateInstanceRequestData;
use App\Dto\EnvironmentInstance;
use Illuminate\Support\Composer;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\number;
use function Laravel\Prompts\search;
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
                            {--visibility-timeout= : Visibility timeout in seconds (0-43200)}
                            {--polling-interval= : Polling interval in seconds (0-60)}
                            {--shutdown-timeout= : Shutdown timeout in seconds}';

    protected $description = 'Create a new managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Create Managed Queue');

        $composer = app(Composer::class)->setWorkingPath(getcwd());

        if (!$composer->hasPackage('aws/aws-sdk-php')) {
            warning('The aws/aws-sdk-php package is required to create a managed queue.');

            if (confirm('Would you like to install it now?')) {
                spin(
                    fn() => $composer->requirePackages(['aws/aws-sdk-php']),
                    'Installing aws/aws-sdk-php...',
                );
            }
        }

        $environment = $this->resolvers()->environment()->from($this->argument('environment'));

        $instance = $this->loopUntilValid(fn() => $this->createManagedQueue($environment->id));

        $this->outputJsonIfWanted($instance);

        success("Managed queue created: {$instance->name}");
    }

    protected function createManagedQueue(string $environmentId)
    {
        $this->form()->prompt(
            'name',
            fn($resolver) => $resolver->fromInput(
                fn($value) => text(
                    label: 'Queue name',
                    hint: 'Must match the queue name your application dispatches to',
                    default: $value ?? 'default',
                    required: true,
                ),
            ),
        );

        $sizes = collect($this->client->instances()->sizes()->all())
            ->filter(fn($size) => str_starts_with($size->name, 'mq-'));

        $this->form()->prompt(
            'size',
            fn($resolver) => $resolver->fromInput(
                fn($value) => search(
                    label: 'Size',
                    options: fn($query) => $sizes
                        ->filter(
                            fn($size) => $query === ''
                                || str_contains(strtolower($size->name), strtolower($query))
                                || str_contains(strtolower($size->description), strtolower($query)),
                        )
                        ->mapWithKeys(fn($size) => [$size->name => $size->description])
                        ->toArray(),
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'max_workers',
            fn($resolver) => $resolver
                ->fromInput(
                    fn($value) => (int) number(
                        label: 'Maximum workers',
                        default: $value ?? '3',
                        min: 1,
                    ),
                )
                ->nonInteractively(fn() => 3),
            'max-workers',
        );

        $this->form()->prompt(
            'visibility_timeout',
            fn($resolver) => $resolver
                ->fromInput(
                    fn($value) => (int) number(
                        label: 'Visibility timeout (seconds)',
                        hint: 'How long an in-flight job is hidden from other workers (0-43200)',
                        default: $value ?? '60',
                        min: 0,
                        max: 43200,
                    ),
                )
                ->nonInteractively(fn() => 60),
            'visibility-timeout',
        );

        $this->form()->prompt(
            'polling_interval',
            fn($resolver) => $resolver
                ->fromInput(
                    fn($value) => (int) number(
                        label: 'Polling interval (seconds)',
                        hint: 'How often to check queue pressure for scaling decisions (0-60)',
                        default: $value ?? '5',
                        min: 0,
                        max: 60,
                    ),
                )
                ->nonInteractively(fn() => 5),
            'polling-interval',
        );

        $this->form()->prompt(
            'shutdown_timeout',
            fn($resolver) => $resolver
                ->fromInput(
                    fn($value) => (int) number(
                        label: 'Shutdown timeout (seconds)',
                        hint: 'Grace period for workers to finish their current job before terminating',
                        default: $value ?? '90',
                        min: 1,
                    ),
                )
                ->nonInteractively(fn() => 90),
            'shutdown-timeout',
        );

        return spin(
            fn() => $this->client->instances()->create(
                new CreateInstanceRequestData(
                    environmentId: $environmentId,
                    name: $this->form()->get('name'),
                    type: 'managed_queue',
                    size: $this->form()->get('size'),
                    scalingType: 'auto',
                    minReplicas: 0,
                    maxReplicas: $this->form()->integer('max_workers'),
                    visibilityTimeout: $this->form()->integer('visibility_timeout'),
                    pollingInterval: $this->form()->integer('polling_interval'),
                    shutdownTimeout: $this->form()->integer('shutdown_timeout'),
                ),
            ),
            'Creating managed queue...',
        );
    }
}
