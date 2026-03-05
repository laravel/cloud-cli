<?php

namespace App\Prompts;

use Laravel\Prompts\Prompt;

class CodeBlock extends Prompt
{
    public function __construct(public string $code, public string $language = 'php')
    {
        //
    }

    public function display(): void
    {
        $this->state = 'submit';
        $this->render();
    }

    public function value(): mixed
    {
        return null;
    }
}
