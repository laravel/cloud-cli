<?php

namespace App\Concerns;

use App\Dto\Command;

use function Laravel\Prompts\warning;

trait WarnsAboutTruncatedOutput
{
    protected function warnIfOutputTruncated(Command $command): void
    {
        if (! $command->outputTruncated) {
            return;
        }

        if (! $command->outputUrl) {
            warning('Output is truncated.');

            return;
        }

        warning('Output is truncated. Download the full output (link expires in a few minutes):'.PHP_EOL.$command->outputUrl);
    }
}
