<?php

namespace App\Client\Resources\DatabaseUsers;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteDatabaseUserRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $clusterId,
        protected string $userId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/users/{$this->userId}";
    }
}
