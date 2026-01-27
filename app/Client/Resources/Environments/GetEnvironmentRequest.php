<?php

namespace App\Client\Resources\Environments;

use App\Dto\Environment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetEnvironmentRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
        protected ?string $include = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'include' => $this->include,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Environment::createFromResponse($response->json());
    }
}
