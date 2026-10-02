<?php

namespace App\Prompts;

use App\Dto\DeploymentLog;
use Laravel\Prompts\Prompt;

class DeploymentLogsPrompt extends Prompt
{
    public function __construct(public DeploymentLog $log)
    {
        //
    }

    public function display(): void
    {
        $this->state = 'submit';
        $this->render();
    }

    public function value(): DeploymentLog
    {
        return $this->log;
    }
}
