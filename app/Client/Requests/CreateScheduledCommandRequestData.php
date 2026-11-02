<?php

namespace App\Client\Requests;

class CreateScheduledCommandRequestData extends RequestData
{
    public function __construct(
        public readonly string $instanceId,
        public readonly string $command,
        public readonly string $schedule,
        public readonly ?string $name = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'command' => $this->command,
            'schedule' => $this->schedule,
        ]);
    }
}
