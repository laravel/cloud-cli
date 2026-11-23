<?php

namespace App\Client\Requests;

class SubmitFeedbackRequestData extends RequestData
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly ?string $tool = null,
        public readonly ?string $environmentId = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'type' => $this->type,
            'message' => $this->message,
            'tool' => $this->tool,
            'environment_id' => $this->environmentId,
        ]);
    }
}
