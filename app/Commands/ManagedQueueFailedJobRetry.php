<?php

namespace App\Commands;

use App\Enums\InstanceType;
use Illuminate\Support\Str;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

class ManagedQueueFailedJobRetry extends BaseCommand
{
    protected $signature = 'managed-queue:retry-failed-job {instance? : The instance ID} {job? : The failed job ID}';

    protected $description = 'Retry a failed job on a managed queue';

    protected $aliases = ['queue:retry-failed-job'];

    public function handle()
    {
        $this->ensureClient();

        intro('Retrying Failed Job');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $jobIds = $this->argument('job') ?? $this->selectFailedJob($instance->id);

        if (! is_array($jobIds)) {
            $jobIds = [$jobIds];
        }

        $jobLabel = Str::plural('job', count($jobIds));

        spin(
            fn () => collect($jobIds)->each(
                fn ($jobId) => $this->client->instances()->retryFailedJob($instance->id, $jobId),
            ),
            "Retrying failed {$jobLabel}...",
        );

        $this->outputJsonIfWanted("Failed {$jobLabel} queued for retry.");

        success("Failed {$jobLabel} queued for retry");
    }

    protected function selectFailedJob(string $instanceId): array
    {
        $jobs = spin(
            fn () => $this->client->instances()->failedJobs($instanceId)->collect(),
            'Fetching failed jobs...',
        );

        if ($jobs->isEmpty()) {
            $this->outputErrorOrThrow('No failed jobs found.');
        }

        $this->ensureInteractive('No failed jobs found. Provide a job ID.');

        return multiselect(
            label: 'Select failed jobs to retry',
            options: $jobs->mapWithKeys(fn ($job) => [
                $job->id => "{$job->name} ({$job->queue}, failed at {$job->failedAt?->format('Y-m-d H:i:s')})",
            ])->toArray(),
        );
    }
}
