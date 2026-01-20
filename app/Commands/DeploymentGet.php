<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use Laravel\Prompts\Concerns\Colors;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class DeploymentGet extends Command
{
    use Colors;
    use HasAClient;

    protected $signature = 'deployment:get {deployment : The deployment ID} {--json : Output as JSON}';

    protected $description = 'Get deployment details';

    public function handle()
    {
        $this->ensureClient();

        intro('Deployment Details');

        $deployment = spin(
            fn () => $this->client->getDeployment($this->argument('deployment')),
            'Fetching deployment...'
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'id' => $deployment->id,
                'status' => $deployment->status->value,
                'branch' => $deployment->branchName,
                'commit' => [
                    'hash' => $deployment->commitHash,
                    'message' => $deployment->commitMessage,
                    'author' => $deployment->commitAuthor,
                ],
                'started_at' => $deployment->startedAt?->toIso8601String(),
                'finished_at' => $deployment->finishedAt?->toIso8601String(),
                'failure_reason' => $deployment->failureReason,
                'total_time' => $deployment->totalTime()->format('%H:%I:%S'),
                'environment_id' => $deployment->environmentId,
            ], JSON_PRETTY_PRINT));

            return;
        }

        $this->info("Deployment: {$deployment->id}");
        $this->line("Status: {$deployment->status->label()}");
        $this->line("Branch: {$deployment->branchName}");
        $this->line("Commit: {$deployment->commitHash}");
        $this->line("Message: {$deployment->commitMessage}");

        if ($deployment->finishedAt) {
            $this->line("Duration: {$deployment->totalTime()->format('%I:%S')}");
        }

        if ($deployment->failureReason) {
            $this->line("Failure: {$deployment->failureReason}");
        }
    }
}
