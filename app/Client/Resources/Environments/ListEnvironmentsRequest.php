<?php

namespace App\Client\Resources\Environments;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Environment;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListEnvironmentsRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $applicationId,
        protected ?string $name = null,
        protected ?string $status = null,
        protected ?string $slug = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/applications/{$this->applicationId}/environments";
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[name]' => $this->name,
                'filter[status]' => $this->status,
                'filter[slug]' => $this->slug,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($environment) => Environment::createFromResponse([
            'data' => $environment,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
