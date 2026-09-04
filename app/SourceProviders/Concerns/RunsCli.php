<?php

namespace App\SourceProviders\Concerns;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

trait RunsCli
{
    protected function run(array $command): ProcessResult
    {
        return Process::run($command);
    }

    protected function json(array $command): array
    {
        $result = $this->run($command);

        if (! $result->successful()) {
            return [];
        }

        return json_decode(trim($result->output()), true) ?: [];
    }
}
