<?php

namespace App\Client\Resources\DatabaseRestores;

use App\Client\Requests\CreateDatabaseRestoreRequestData;
use App\Dto\DatabaseCluster;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateDatabaseRestoreRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected CreateDatabaseRestoreRequestData $data,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->data->clusterId}/restore";
    }

    protected function defaultBody(): array
    {
        return $this->data->toRequestData();
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return DatabaseCluster::createFromResponse($response->json());
    }
}
