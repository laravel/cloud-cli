<?php

namespace App\Client\Resources\Databases;

use App\Dto\Database;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateDatabaseRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $clusterId,
        protected string $name,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/databases";
    }

    protected function defaultBody(): array
    {
        return [
            'name' => $this->name,
        ];
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Database::createFromResponse($response->json());
    }
}
