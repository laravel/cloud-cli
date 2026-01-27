<?php

namespace App\Client\Resources\Applications;

use App\Dto\Application;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetApplicationRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $applicationId,
        protected ?string $include = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->applicationId}";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'include' => $this->include,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Application::createFromResponse($response->json());
    }
}
