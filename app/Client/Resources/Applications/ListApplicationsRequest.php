<?php

namespace App\Client\Resources\Applications;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListApplicationsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected ?string $include = null,
        protected ?string $name = null,
        protected ?string $region = null,
        protected ?string $slug = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/applications';
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'include' => $this->include,
            'filter[name]' => $this->name,
            'filter[region]' => $this->region,
            'filter[slug]' => $this->slug,
        ]);
    }
}
