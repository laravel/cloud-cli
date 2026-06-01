<?php

namespace App\Commands;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class ManagedQueueFailedJobRetry extends BaseCommand
{
    protected $signature = 'managed-queue:retry-failed-job {instance? : The instance ID} {job? : The failed job ID}';

    protected $description = 'Retry a failed job on a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Retry Failed Job');

        $instance = $this->resolvers()->instance()->from($this->argument('instance'));

        $jobId = $this->argument('job') ?? $this->selectFailedJob($instance->id);

        spin(
            fn () => $this->client->instances()->retryFailedJob($instance->id, $jobId),
            'Retrying failed job...',
        );

        $this->outputJsonIfWanted('Failed job queued for retry.');

        success('Failed job queued for retry');
    }

    protected function selectFailedJob(string $instanceId): string
    {
        $jobs = spin(
            fn () => $this->client->instances()->failedJobs($instanceId)->collect(),
            'Fetching failed jobs...',
        );

        if ($jobs->isEmpty()) {
            $this->outputErrorOrThrow('No failed jobs found.');
        }

        $this->ensureInteractive('No failed jobs found. Provide a job ID.');

        return select(
            label: 'Failed Job',
            options: $jobs->mapWithKeys(fn ($job) => [
                $job->id => $job->queue.' — '.($job->failedAt?->format('Y-m-d H:i:s') ?? 'unknown'),
            ])->toArray(),
        );
    }
}
