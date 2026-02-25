<?php

namespace App\Prompts;

use Laravel\Prompts\Output\ConsoleOutput;

class SuppressedOutput extends ConsoleOutput
{
    public function writeDirectly(string $message): void
    {
        if (Renderer::$suppressOutput) {
            return;
        }

        parent::writeDirectly($message);
    }
}
