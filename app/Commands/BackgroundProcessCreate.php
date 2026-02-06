<?php

namespace App\Commands;

use App\Client\Requests\CreateBackgroundProcessRequestData;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class BackgroundProcessCreate extends BaseCommand
{
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
                            {--processes=1 : Number of processes}
                            {--json : Output as JSON}';

    protected $description = 'Create a new background process';

    public function handle()
    {
        $this->ensureClient();

        intro('Creating Background Process');

        $process = $this->loopUntilValid($this->createBackgroundProcess(...));

        $this->outputJsonIfWanted($process);

        outro("Background process created: {$process->id}");
    }

    protected function createBackgroundProcess()
    {
        $instanceId = $this->resolvers()->instance()->from($this->argument('instance'))->id;

        $this->fields()->add(
            'type',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => select(
                    label: 'Process type',
                    options: [
                        'worker' => 'Worker',
                        'custom' => 'Custom',
                    ],
                    default: $value ?? 'worker',
                    required: true,
                )),
        );

        if ($this->fields()->get('type') === 'custom') {
            $this->fields()->add(
                'command',
                fn($resolver) => $resolver->fromInput(
                    fn(?string $value) => text(
                        label: 'Command',
                        default: $value ?? 'php artisan ',
                        required: true,
                    ),
                ),
            );
        } elseif ($this->wantsToEditWorkerDefaults()) {
            $this->addWorkerParams();
        }

        $this->fields()->add(
            'processes',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Number of processes',
                    default: $value ?? '1',
                    required: true,
                    validate: fn($value) => match (true) {
                        ! is_numeric($value) => 'The number of processes must be a number.',
                        $value < 1 => 'The number of processes must be at least 1.',
                        $value > 10 => 'The number of processes must be less than or equal to 10.',
                        default => null,
                    },
                ))
                ->nonInteractively(fn() => '1'),
        );

        $type = $this->fields()->get('type');
        $processes = (int) $this->fields()->get('processes');
        $config = null;
        $command = null;

        if ($type === 'worker') {
            $config = [
                'queue' => $this->fields()->get('queue', $this->getWorkerDefult('queue')),
                'connection' => $this->fields()->get('connection', $this->getWorkerDefult('connection')),
                'tries' => $this->fields()->get('tries', $this->getWorkerDefult('tries')),
                'backoff' => $this->fields()->get('backoff', $this->getWorkerDefult('backoff')),
                'sleep' => $this->fields()->get('sleep', $this->getWorkerDefult('sleep')),
                'rest' => $this->fields()->get('rest', $this->getWorkerDefult('rest')),
                'timeout' => $this->fields()->get('timeout', $this->getWorkerDefult('timeout')),
                'force' => $this->fields()->get('force', $this->getWorkerDefult('force')),
            ];
        } else {
            $command = $this->fields()->get('command');
        }

        return spin(
            fn() => $this->client->backgroundProcesses()->create(new CreateBackgroundProcessRequestData(
                instanceId: $instanceId,
                type: $type,
                processes: $processes,
                command: $command,
                config: $config,
            )),
            'Creating background process...',
        );
    }

    protected function addWorkerParams(): void
    {
        $this->fields()->add(
            'connection',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Connection',
                    default: $value ?? $this->getWorkerDefult('connection'),
                    required: true,
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('connection'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'queue',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Queue',
                    default: $value ?? $this->getWorkerDefult('queue'),
                    required: true,
                    hint: 'Comma-separated for multiple queues',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('queue'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'tries',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Tries',
                    default: $value ?? $this->getWorkerDefult('tries'),
                    validate: fn($value) => match (true) {
                        ! is_numeric($value) => 'The number of tries must be a number.',
                        default => null,
                    },
                    required: true,
                    hint: 'Number of times a job should be attempted',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('tries'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'backoff',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Backoff',
                    default: $value ?? $this->getWorkerDefult('backoff'),
                    validate: fn($value) => match (true) {
                        ! is_numeric($value) => 'The backoff time must be a number.',
                        default => null,
                    },
                    required: true,
                    hint: 'Number of seconds to wait before retrying a failed job.',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('backoff'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'sleep',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Sleep',
                    default: $value ?? $this->getWorkerDefult('sleep'),
                    validate: fn($value) => match (true) {
                        ! is_numeric($value) => 'The sleep time must be a number.',
                        default => null,
                    },
                    required: true,
                    hint: 'Number of seconds to sleep when no jobs are available',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('sleep'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'rest',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Rest',
                    default: $value ?? $this->getWorkerDefult('rest'),
                    validate: fn($value) => match (true) {
                        ! is_numeric($value) => 'The rest time must be a number.',
                        default => null,
                    },
                    required: true,
                    hint: 'Number of seconds to rest between jobs',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('rest'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'timeout',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => text(
                    label: 'Timeout',
                    default: $value ?? $this->getWorkerDefult('timeout'),
                    validate: fn($value) => match (true) {
                        ! is_numeric($value) => 'The timeout time must be a number.',
                        default => null,
                    },
                    required: true,
                    hint: 'Number of seconds a job can run before timing out',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('timeout'))
                ->shouldPromptOnce(),
        );

        $this->fields()->add(
            'force',
            fn($resolver) => $resolver
                ->fromInput(fn(?string $value) => confirm(
                    label: 'Run in maintenance mode?',
                    default: $value ?? $this->getWorkerDefult('force'),
                    hint: 'Force the worker to run even in maintenance mode',
                ))
                ->nonInteractively(fn() => $this->getWorkerDefult('force'))
                ->shouldPromptOnce(),
        );
    }

    protected function wantsToEditWorkerDefaults(): bool
    {
        if (! $this->isInteractive()) {
            return false;
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
