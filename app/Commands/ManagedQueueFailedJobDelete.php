<?php

namespace App\Commands;

use App\Enums\InstanceType;
use Illuminate\Support\Str;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class ManagedQueueFailedJobDelete extends BaseCommand
{
    protected $signature = 'managed-queue:delete-failed-job {instance? : The instance ID} {job? : The failed job ID} {--force : Skip confirmation}';

    protected $description = 'Delete a failed job from a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Delete Failed Job');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $jobId = $this->argument('job') ?? $this->selectFailedJob($instance->id);

        $this->confirmDestructive("Delete failed job '{$jobId}'?");

        spin(
            fn () => $this->client->instances()->deleteFailedJob($instance->id, $jobId),
            'Deleting failed job...',
        );

        $this->outputJsonIfWanted('Failed job deleted.');

        success('Failed job deleted');
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
                    function ($row) use (&$jobId) {
                        $jobId = $row[0];
                    },
                    'Select',
                ],
            ],
        );

        return $jobId ?? '';
    }
}
