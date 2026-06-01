<?php

namespace App\Client\Resources\Instances;

use App\Dto\ManagedQueueFailedJob;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;

class ListManagedQueueFailedJobsRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $instanceId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/instances/{$this->instanceId}/failed-jobs";
    }

    public function createDtoFromResponse(Response $response): mixed
    {
        return array_map(
            fn ($job) => ManagedQueueFailedJob::createFromResponse(['data' => $job]),
            $response->json('data'),
        );
    }
}
