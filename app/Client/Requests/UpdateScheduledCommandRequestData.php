<?php

namespace App\Client\Requests;

class UpdateScheduledCommandRequestData extends RequestData
{
    public function __construct(
        public readonly string $scheduledCommandId,
        public readonly ?string $name = null,
        public readonly ?string $command = null,
        public readonly ?string $schedule = null,
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
