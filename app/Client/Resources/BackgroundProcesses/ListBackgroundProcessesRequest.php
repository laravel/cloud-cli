<?php

namespace App\Client\Resources\BackgroundProcesses;

use App\Dto\BackgroundProcess;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListBackgroundProcessesRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $instanceId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/instances/{$this->instanceId}/background-processes";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(fn ($backgroundProcess) => BackgroundProcess::createFromResponse([
            'data' => $backgroundProcess,
            'included' => $response->json('included', []),
        ]), $response->json('data'));
    }
}
