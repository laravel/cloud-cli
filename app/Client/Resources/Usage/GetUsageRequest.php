<?php

namespace App\Client\Resources\Usage;

use App\Dto\Usage;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetUsageRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected int $period = 0,
        protected ?string $environment = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/usage';
    }

    protected function defaultQuery(): array
    {
        $query = ['period' => $this->period];

        if ($this->environment !== null) {
            $query['environment'] = $this->environment;
        }

        return $query;
    }

    public function createDtoFromResponse(Response $response): Usage
    {
        return Usage::createFromResponse($response->json());
    }
}
