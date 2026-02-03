<?php

namespace App\Commands;

use App\Concerns\HasAClient;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class CommandGet extends BaseCommand
{
    use HasAClient;

    protected $signature = 'command:get {commandId? : The command ID} {--json : Output as JSON}';

    protected $description = 'Get command details';

    public function handle()
    {
        $this->ensureClient();

        intro('Command Details');

        $cmd = $this->resolvers()->command()->from($this->argument('commandId'));

        $this->outputJsonIfWanted($cmd);

        dataList($cmd->descriptiveArray());

        outro('');
    }
}
