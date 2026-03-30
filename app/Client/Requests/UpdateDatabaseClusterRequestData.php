<?php

namespace App\Client\Requests;

class UpdateDatabaseClusterRequestData extends RequestData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public readonly string $clusterId,
        public readonly array $config,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'config' => $this->config,
        ];
    }
}
