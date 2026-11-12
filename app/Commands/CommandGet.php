<?php

namespace App\Commands;

use App\Concerns\InteractsWithClipbboard;
use App\Concerns\WarnsAboutTruncatedOutput;
use App\Dto\Command;

use function Laravel\Prompts\intro;

class CommandGet extends BaseCommand
{
    protected ?string $jsonDataClass = Command::class;

    use InteractsWithClipbboard;
    use WarnsAboutTruncatedOutput;

    protected $signature = 'command:get {commandId? : The command ID} {--copy-output : Copy the output to the clipboard}';

    protected $description = 'Get command details';

    protected $aliases = ['cmd:get'];

    public function handle()
    {
        $this->ensureClient();

        intro('Command Details');

        $cmd = $this->resolvers()->command()->from($this->argument('commandId'));

        $this->outputJsonIfWanted($cmd);

        dataList(array_filter([
            'ID' => $cmd->id,
            'Command' => $cmd->command,
            'Status' => $cmd->status->label(),
            'Exit Code' => $cmd->exitCode ?? '—',
            'Failure Reason' => $cmd->failureReason,
            'Output' => $cmd->output ?? '—',
            'Started At' => $cmd->startedAt?->format('Y-m-d H:i:s') ?? '—',
            'Finished At' => $cmd->finishedAt?->format('Y-m-d H:i:s') ?? '—',
            'Created At' => $cmd->createdAt?->format('Y-m-d H:i:s') ?? '—',
        ], fn ($value) => $value !== null));

        $this->warnIfOutputTruncated($cmd);

        if ($this->option('copy-output')) {
            $this->copyToClipboard($cmd->output ?? '');
            success('Output copied to clipboard');
        }
    }
}
