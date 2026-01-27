<?php

namespace App\Client\Resources\Environments;

use App\Dto\Environment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListEnvironmentsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $applicationId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->applicationId}/environments";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($environment) => Environment::createFromResponse([
            'data' => $environment,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
