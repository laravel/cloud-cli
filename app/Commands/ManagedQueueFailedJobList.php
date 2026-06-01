<?php

namespace App\Commands;

use App\Dto\ManagedQueueFailedJob;
use Laravel\Prompts\Key;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class ManagedQueueFailedJobList extends BaseCommand
{
    protected ?string $jsonDataClass = ManagedQueueFailedJob::class;

    protected bool $jsonDataIsCollection = true;

    protected $signature = 'managed-queue:failed-jobs {instance? : The instance ID}';

    protected $description = 'List failed jobs for a managed queue';

    public function handle()
    {
        $this->ensureClient();

        intro('Failed Jobs');

        $instance = $this->resolvers()->instance()->ofType('managed_queue')->from($this->argument('instance'));

        $jobs = spin(
            fn() => $this->client->instances()->failedJobs($instance->id),
            'Fetching failed jobs...',
        );

        $items = $jobs->collect();

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No failed jobs found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Queue', 'Attempts', 'Failed At'],
            rows: $items->map(fn($job) => [
                $job->id,
                $job->queue,
                $job->attempts,
                $job->failedAt?->format('Y-m-d H:i:s') ?? '-',
            ])->toArray(),
            actions: [
                Key::ENTER => [
                    fn($row) => $this->call('managed-queue:retry-failed-job', [
                        'instance' => $instance->id,
                        'job' => $row[0],
                    ]),
                    'Retry',
                ],
            ],
        );
    }
}
