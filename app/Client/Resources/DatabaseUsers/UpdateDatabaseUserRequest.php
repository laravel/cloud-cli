<?php

namespace App\Client\Resources\DatabaseUsers;

use App\Client\Requests\UpdateDatabaseUserRequestData;
use App\Dto\DatabaseUser;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class UpdateDatabaseUserRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        protected UpdateDatabaseUserRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->data->clusterId}/users/{$this->data->userId}";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): DatabaseUser
    {
        return DatabaseUser::createFromResponse($response->json());
    }
}
