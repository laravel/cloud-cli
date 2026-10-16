<?php

namespace App\Client\Resources\Commands;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\Command;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListCommandsRequest extends Request implements Paginatable
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $environmentId,
        protected ?string $status = null,
        protected ?string $command = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/environments/{$this->environmentId}/commands";
    }

    protected function defaultQuery(): array
    {
        return array_merge(
            $this->includeQuery(),
            array_filter([
                'filter[status]' => $this->status,
                'filter[command]' => $this->command,
            ]),
        );
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($command) => Command::createFromResponse([
            'data' => $command,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
