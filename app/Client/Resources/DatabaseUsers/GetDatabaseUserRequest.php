<?php

namespace App\Client\Resources\DatabaseUsers;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DatabaseUser;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDatabaseUserRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

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

    public function createDtoFromResponse(Response $response): DatabaseUser
    {
        return DatabaseUser::createFromResponse($response->json());
    }
}
