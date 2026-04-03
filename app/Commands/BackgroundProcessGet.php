<?php

namespace App\Commands;

use App\Dto\BackgroundProcess;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;

class BackgroundProcessGet extends BaseCommand
{
    protected ?string $jsonDataClass = BackgroundProcess::class;

    protected $signature = 'background-process:get {process? : The background process ID}';

    protected $description = 'Get background process details';

    protected $aliases = ['process:get'];

    public function handle()
    {
        $this->ensureClient();

        intro('Background Process Details');

        $process = $this->resolvers()->backgroundProcess()->from($this->argument('process'));

        $this->outputJsonIfWanted($process);

        info("Background Process: {$process->id}");

        dataList([
            'ID' => $process->id,
            'Type' => $process->type,
            'Processes' => $process->processes,
            'Command' => $process->command,
            'Strategy Type' => $process->strategyType,
            'Strategy Threshold' => $process->strategyThreshold,
            'Connection' => $process->connection,
            'Queue' => $process->queue,
            'Tries' => $process->tries,
            'Backoff' => $process->backoff,
            'Sleep' => $process->sleep,
            'Rest' => $process->rest,
            'Timeout' => $process->timeout,
            'Force (maintenance mode)' => $process->force !== null ? ($process->force ? 'Yes' : 'No') : '—',
            'Created At' => $process->createdAt?->format('Y-m-d H:i:s') ?? '—',
            'Instance ID' => $process->instanceId,
        ]);
    }
}
