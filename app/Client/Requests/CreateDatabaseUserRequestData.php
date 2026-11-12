<?php

namespace App\Client\Requests;

class CreateDatabaseUserRequestData extends RequestData
{
    /**
     * @param  list<string>  $databases  Database (schema) IDs
     */
    public function __construct(
        public readonly string $clusterId,
        public readonly string $username,
        public readonly string $type,
        public readonly array $databases,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return [
            'username' => $this->username,
            'type' => $this->type,
            'databases' => $this->databases,
        ];
    }
}
