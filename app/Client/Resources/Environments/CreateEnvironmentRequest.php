<?php

namespace App\Client\Resources\Environments;

use App\Dto\Environment;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateEnvironmentRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        protected string $applicationId,
        protected string $name,
        protected ?string $branch = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->applicationId}/environments";
    }

    protected function defaultBody(): array
    {
        return array_filter([
            'name' => $this->name,
            'branch' => $this->branch,
        ]);
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return Environment::createFromResponse($response->json());
    }
}
