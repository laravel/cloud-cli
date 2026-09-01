<?php

namespace App\Concerns;

use App\Support\Stdin;

trait AcceptsPipedInput
{
    /**
     * Let a sensitive option be piped in instead of typed, so its value never lands
     * in shell history or the process list. Must run before form() is first built.
     */
    protected function fillOptionFromStdin(string $option): void
    {
        if (filled($this->option($option))) {
            return;
        }

        $piped = app(Stdin::class)->read();

        if ($piped === null) {
            return;
        }

        $this->input->setOption($option, $piped);
    }
}
