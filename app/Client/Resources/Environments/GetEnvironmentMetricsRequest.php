<?php

namespace App\Client\Resources\Environments;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetEnvironmentMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/metrics";
    }

    public function createDtoFromResponse(Response $response): array
    {
        return $response->json('data') ?? $response->json();
    }
}
