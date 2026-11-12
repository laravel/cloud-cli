<?php

namespace App\Client\Resources\DatabaseUsers;

use App\Client\Requests\CreateDatabaseUserRequestData;
use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DatabaseUser;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateDatabaseUserRequest extends Request implements HasBody
{
    use AcceptsInclude;
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected CreateDatabaseUserRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->data->clusterId}/users";
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
