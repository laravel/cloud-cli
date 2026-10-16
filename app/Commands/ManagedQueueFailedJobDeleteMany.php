<?php

namespace App\Commands;

use App\Enums\InstanceType;
use App\Exceptions\CommandExitException;
use Illuminate\Support\Collection;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ManagedQueueFailedJobDeleteMany extends BaseCommand
{
    protected $signature = 'managed-queue:delete-failed-jobs
                            {instance? : The instance ID}
                            {--job=* : A failed job ID to delete (repeatable)}
                            {--failed-before= : Delete every job that failed before this date and time}
                            {--force : Skip confirmation}';

    protected $description = 'Delete failed jobs from a managed queue in bulk';

    protected $aliases = ['queue:delete-failed-jobs'];

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Failed Jobs');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $jobIds = $this->jobIds();
        $failedBefore = $this->option('failed-before');

        if ($jobIds->isEmpty() && $failedBefore === null) {
            $this->ensureInteractive('Provide --job or --failed-before.');

            [$jobIds, $failedBefore] = $this->promptForJobs($instance->id);
        }

        $this->confirmDestructive(
            $failedBefore !== null
                ? "Delete every job that failed before {$failedBefore} on '{$instance->name}'?"
                : "Delete {$jobIds->count()} failed ".str('job')->plural($jobIds->count())." from '{$instance->name}'?",
        );

        spin(
            fn () => $this->client->instances()->deleteFailedJobs($instance->id, $jobIds->all(), $failedBefore),
            'Deleting failed jobs...',
        );

        $this->outputJsonIfWanted('Failed jobs queued for deletion.');

        success('Failed jobs queued for deletion');
    }

    /**
     * @return Collection<int, string>
     */
    protected function jobIds(): Collection
    {
        // Accept comma-separated values too, since that is how the API takes them.
        return collect($this->option('job'))
            ->flatMap(fn (string $value) => explode(',', $value))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return array{0: Collection<int, string>, 1: string|null}
     */
    protected function promptForJobs(string $instanceId): array
    {
        $mode = select(
            label: 'Which failed jobs do you want to delete?',
            options: [
                'select' => 'Choose jobs from the list',
                'before' => 'Every job that failed before a date and time',
            ],
        );

        if ($mode === 'before') {
            $failedBefore = $this->form()->prompt(
                'failed_before',
                fn ($resolver) => $resolver->fromInput(
                    fn ($value) => text(
                        label: 'Delete jobs that failed before',
                        default: $value ?? now()->toIso8601String(),
                        required: true,
                    ),
                ),
            )->value();

            return [collect(), $failedBefore];
        }

        $jobs = spin(
            fn () => $this->client->instances()->failedJobs($instanceId)->collect(),
            'Fetching failed jobs...',
        );

        if ($jobs->isEmpty()) {
            $this->outputErrorOrThrow('No failed jobs found.');

            throw new CommandExitException(self::FAILURE);
        }

        $selected = multiselect(
            label: 'Select failed jobs to delete',
            options: $jobs->mapWithKeys(fn ($job) => [
                $job->id => "{$job->name} ({$job->queue}, failed at {$job->failedAt?->format('Y-m-d H:i:s')})",
            ])->toArray(),
            required: true,
        );

        return [collect($selected)->values(), null];
    }
}
