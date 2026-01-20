<?php

namespace App\Prompts;

use Laravel\Prompts\Prompt;

class DataList extends Prompt
{
    public function __construct(public array $data)
    {
        //
    }

    public function display(): void
    {
        $this->state = 'submit';
        $this->render();
    }

    public function value(): array
    {
        return $this->data;
    }
}
