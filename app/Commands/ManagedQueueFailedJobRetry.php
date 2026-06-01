<?php

namespace App\Commands;

use App\Enums\InstanceType;
use Illuminate\Support\Str;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ManagedQueueFailedJobRetry extends BaseCommand
{
    protected $signature = 'managed-queue:retry-failed-job {instance? : The instance ID} {job? : The failed job ID}';

    protected $description = 'Retry a failed job on a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Retry Failed Job');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

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

        $jobId = null;

        dataTable(
            headers: ['ID', 'Name', 'Queue', 'Exception', 'Failed At'],
            rows: $jobs->map(fn ($job) => [
                $job->id,
                $job->name,
                $job->queue,
                Str::limit($job->exception ?? '-', 30),
                $job->failedAt?->format('Y-m-d H:i:s') ?? '-',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn ($row) => $row[0],
                    'Select',
                ],
            ],
        );

        return $jobId ?? '';
    }
}
