<?php

namespace App\Commands;

use App\Client\Requests\CreateBackgroundProcessRequestData;
use App\Dto\BackgroundProcess;
use App\Dto\EnvironmentInstance;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\number;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BackgroundProcessCreate extends BaseCommand
{
    protected ?string $jsonDataClass = BackgroundProcess::class;

    protected $signature = 'background-process:create
                            {instance? : The instance ID}
                            {--type= : Process type (worker|custom)}
                            {--command= : The command to run (only for custom processes)}
                            {--connection=database : Queue connection}
                            {--queue=default : Queue name}
                            {--backoff=30 : Backoff time}
                            {--sleep=10 : Sleep time}
                            {--rest=0 : Rest time}
                            {--timeout=60 : Timeout time}
                            {--tries=1 : Number of tries}
                            {--force=0 : Force the process to run in maintenance mode}
                            {--processes=1 : Number of processes}';

    protected $description = 'Create a new background process';

    protected $aliases = ['process:create'];

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Background Process');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $process = $this->loopUntilValid(fn () => $this->createBackgroundProcess($instance));

        $this->outputJsonIfWanted($process);

        success("Background process created: {$process->id}");
    }

    protected function createBackgroundProcess(EnvironmentInstance $instance)
    {
        $this->form()->prompt(
            'type',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => select(
                    label: 'Process type',
                    options: [
                        'worker' => 'Worker',
                        'custom' => 'Custom',
                    ],
                    default: $value ?? 'worker',
                    required: true,
                )),
        );

        if ($this->form()->get('type') === 'custom') {
            $this->form()->prompt(
                'command',
                fn ($resolver) => $resolver->fromInput(
                    fn (?string $value) => text(
                        label: 'Command',
                        default: $value ?? 'php artisan ',
                        required: true,
                    ),
                ),
            );
        } elseif ($this->wantsToEditWorkerDefaults()) {
            $this->addWorkerParams();
        }

        $this->form()->prompt(
            'processes',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Number of processes',
                    default: $value ?? '1',
                    required: true,
                    min: 1,
                    max: 10,
                ))
                ->nonInteractively(fn () => 1),
        );

        $type = $this->form()->get('type');
        $processes = (int) $this->form()->get('processes');

        if ($type === 'worker') {
            $config = collect([
                'queue',
                'connection',
                'tries',
                'backoff',
                'sleep',
                'rest',
                'timeout',
                'force',
            ])->mapWithKeys(fn ($key) => [
                $key => $this->form()->get('config.'.$key, $this->getWorkerDefult($key)),
            ])->toArray();
        } else {
            $command = $this->form()->get('command');
        }

        return spin(
            fn () => $this->client->backgroundProcesses()->create(
                new CreateBackgroundProcessRequestData(
                    instanceId: $instance->id,
                    type: $type,
                    processes: $processes,
                    command: $command ?? null,
                    config: $config ?? null,
                ),
            ),
            'Creating background process...',
        );
    }

    protected function addWorkerParams(): void
    {
        $this->form()->prompt(
            'config.connection',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Connection',
                    default: $value ?? $this->getWorkerDefult('connection'),
                    required: true,
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('connection'))
                ->shouldPromptOnce(),
            'connection',
        );

        $this->form()->prompt(
            'config.queue',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => text(
                    label: 'Queue',
                    default: $value ?? $this->getWorkerDefult('queue'),
                    required: true,
                    hint: 'Comma-separated for multiple queues',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('queue'))
                ->shouldPromptOnce(),
            'queue',
        );

        $this->form()->prompt(
            'config.tries',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Tries',
                    default: $value ?? $this->getWorkerDefult('tries'),
                    required: true,
                    hint: 'Number of times a job should be attempted',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('tries'))
                ->shouldPromptOnce(),
            'tries',
        );

        $this->form()->prompt(
            'config.backoff',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Backoff',
                    default: $value ?? $this->getWorkerDefult('backoff'),
                    required: true,
                    hint: 'Number of seconds to wait before retrying a failed job.',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('backoff'))
                ->shouldPromptOnce(),
            'backoff',
        );

        $this->form()->prompt(
            'config.sleep',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Sleep',
                    default: $value ?? $this->getWorkerDefult('sleep'),
                    required: true,
                    hint: 'Number of seconds to sleep when no jobs are available',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('sleep'))
                ->shouldPromptOnce(),
            'sleep',
        );

        $this->form()->prompt(
            'config.rest',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Rest',
                    default: $value ?? $this->getWorkerDefult('rest'),
                    required: true,
                    hint: 'Number of seconds to rest between jobs',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('rest'))
                ->shouldPromptOnce(),
            'rest',
        );

        $this->form()->prompt(
            'config.timeout',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => number(
                    label: 'Timeout',
                    default: $value ?? $this->getWorkerDefult('timeout'),
                    required: true,
                    hint: 'Number of seconds a job can run before timing out',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('timeout'))
                ->shouldPromptOnce(),
            'timeout',
        );

        $this->form()->prompt(
            'config.force',
            fn ($resolver) => $resolver
                ->fromInput(fn (?string $value) => confirm(
                    label: 'Run in maintenance mode?',
                    default: (bool) ($value ?? $this->getWorkerDefult('force')),
                    hint: 'Force the worker to run even in maintenance mode',
                ))
                ->nonInteractively(fn () => $this->getWorkerDefult('force'))
                ->shouldPromptOnce(),
            'force',
        );
    }

    protected function wantsToEditWorkerDefaults(): bool
    {
        if (! $this->isInteractive()) {
            return false;
        }

        foreach ($this->errors->all() as $field => $message) {
            if (str_contains($field, 'config.')) {
                return true;
            }
        }

        dataList([
            'Connection' => $this->getWorkerDefult('connection'),
            'Queue' => $this->getWorkerDefult('queue'),
            'Tries' => $this->getWorkerDefult('tries'),
            'Backoff' => $this->getWorkerDefult('backoff'),
            'Sleep' => $this->getWorkerDefult('sleep'),
            'Rest' => $this->getWorkerDefult('rest'),
            'Timeout' => $this->getWorkerDefult('timeout'),
            'Force to run in maintenance mode' => $this->getWorkerDefult('force') ? 'Yes' : 'No',
        ]);

        return confirm(
            label: 'Do you want to edit the worker defaults?',
            default: false,
        );
    }

    protected function getWorkerDefult(string $key): ?string
    {
        $arg = $this->option($key);

        return match ($key) {
            'force' => (int) ($arg ?? 0) === 1,
            default => $arg ?? null,
        };
    }
}
