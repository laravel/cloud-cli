<?php

namespace App\Client\Resources\ScheduledCommands;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteScheduledCommandRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $scheduledCommandId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/scheduled-commands/{$this->scheduledCommandId}";
    }
}
