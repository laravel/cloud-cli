<?php

namespace App\Commands;

use App\Enums\InstanceType;
use Illuminate\Support\Str;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

class ManagedQueueFailedJobDelete extends BaseCommand
{
    protected $signature = 'managed-queue:delete-failed-job {instance? : The instance ID} {job? : The failed job ID} {--force : Skip confirmation}';

    protected $description = 'Delete a failed job from a managed queue';

    protected $aliases = ['queue:delete-failed-job'];

    public function handle()
    {
        $this->ensureClient();

        intro('Deleting Failed Job');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $jobIds = $this->argument('job') ?? $this->selectFailedJob($instance->id);

        if (! is_array($jobIds)) {
            $jobIds = [$jobIds];
        }

        $jobLabel = Str::plural('job', count($jobIds));

        $this->confirmDestructive("Delete failed {$jobLabel}?");

        spin(
            fn () => collect($jobIds)->each(
                fn ($jobId) => $this->client->instances()->deleteFailedJob($instance->id, $jobId),
            ),
            "Deleting failed {$jobLabel}...",
        );

        $this->outputJsonIfWanted("Failed {$jobLabel} deleted.");

        success("Failed {$jobLabel} deleted");
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
            label: 'Select failed jobs to delete',
            options: $jobs->mapWithKeys(fn ($job) => [
                $job->id => "{$job->name} ({$job->queue}, failed at {$job->failedAt?->format('Y-m-d H:i:s')})",
            ])->toArray(),
        );
    }
}
