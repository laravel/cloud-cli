<?php

namespace App\Commands;

use App\Dto\ManagedQueueFailedJob;
use App\Enums\InstanceType;
use Illuminate\Support\Str;
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

    protected $aliases = ['queue:failed-jobs'];

    public function handle()
    {
        $this->ensureClient();

        intro('Failed Jobs');

        $instance = $this->resolvers()->instance()->ofType(InstanceType::MANAGED_QUEUE)->from($this->argument('instance'));

        $items = spin(
            fn () => $this->client->instances()->failedJobs($instance->id)->collect(),
            'Fetching failed jobs...',
        );

        $this->outputJsonIfWanted($items);

        if ($items->isEmpty()) {
            warning('No failed jobs found.');

            return self::SUCCESS;
        }

        dataTable(
            headers: ['ID', 'Name', 'Queue', 'Exception', 'Failed At'],
            rows: $items->map(fn ($job) => [
                $job->id,
                $job->name,
                $job->queue,
                Str::limit($job->exception ?? '-', 30),
                $job->failedAt?->format('Y-m-d H:i:s') ?? '-',
            ])->toArray(),
            actions: [
                'r' => [
                    fn ($row) => $this->call('managed-queue:retry-failed-job', [
                        'instance' => $instance->id,
                        'job' => $row[0],
                    ]),
                    'Retry',
                ],
                'd' => [
                    fn ($row) => $this->call('managed-queue:delete-failed-job', [
                        'instance' => $instance->id,
                        'job' => $row[0],
                    ]),
                    'Delete',
                ],
                Key::ENTER => [
                    fn ($row) => $this->showJobDetail($items->firstWhere('id', $row[0])),
                    'Details',
                ],
            ],
        );
    }

    protected function showJobDetail(ManagedQueueFailedJob $job)
    {
        dataList([
            'ID' => $job->id,
            'Name' => $job->name,
            'Queue' => $job->queue,
            'Attempts' => $job->attempts,
            'Exception' => $job->exception ?? '-',
            'Failed At' => $job->failedAt?->format('Y-m-d H:i:s') ?? '-',
            'Started At' => $job->startedAt?->format('Y-m-d H:i:s') ?? '-',
            'Retried At' => $job->retriedAt?->format('Y-m-d H:i:s') ?? '-',
            'Retry Reserved Until' => $job->retryReservedUntil?->format('Y-m-d H:i:s') ?? '-',
        ]);
    }
}
