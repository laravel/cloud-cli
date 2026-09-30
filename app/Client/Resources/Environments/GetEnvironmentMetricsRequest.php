<?php

namespace App\Client\Resources\Environments;

use App\Dto\EnvironmentMetrics;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetEnvironmentMetricsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
        protected ?string $period = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/metrics";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'period' => $this->period,
        ]);
    }

    public function createDtoFromResponse(Response $response): EnvironmentMetrics
    {
        return EnvironmentMetrics::createFromResponse($response->json());
    }
}
