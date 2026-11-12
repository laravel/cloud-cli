<?php

namespace App\Client\Requests;

class UpdateDatabaseUserRequestData extends RequestData
{
    /**
     * @param  list<string>  $databases  Database (schema) IDs; replaces the user's current access
     */
    public function __construct(
        public readonly string $clusterId,
        public readonly string $userId,
        public readonly string $type,
        public readonly array $databases,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'type' => $this->type,
            'databases' => $this->databases,
        ];
    }
}
