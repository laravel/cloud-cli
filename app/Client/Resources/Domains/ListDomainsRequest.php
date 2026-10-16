<?php

namespace App\Client\Resources\Domains;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Domain;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListDomainsRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
        protected ?string $name = null,
        protected ?string $hostnameStatus = null,
        protected ?string $sslStatus = null,
        protected ?string $originStatus = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/domains";
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[name]' => $this->name,
                'filter[hostname_status]' => $this->hostnameStatus,
                'filter[ssl_status]' => $this->sslStatus,
                'filter[origin_status]' => $this->originStatus,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($domain) => Domain::createFromResponse([
            'data' => $domain,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
