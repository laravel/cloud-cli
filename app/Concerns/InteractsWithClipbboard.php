<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Process;

trait InteractsWithClipbboard
{
    protected function copyToClipboard(string $text): void
    {
        Process::run(sprintf('echo %s | pbcopy', escapeshellarg($text)));
    }
}
