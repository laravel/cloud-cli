<?php

namespace App\Client\Resources\Resend;

use App\Dto\ResendDomain;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListResendDomainsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $name = null,
        protected ?string $status = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/resend/domains';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'filter[name]' => $this->name,
            'filter[status]' => $this->status,
        ]);
    }

    public function createDtoFromResponse(Response $response): array
    {
        return array_map(
            fn (array $item) => ResendDomain::createFromResponse(['data' => $item]),
            $response->json('data') ?? [],
        );
    }
}
