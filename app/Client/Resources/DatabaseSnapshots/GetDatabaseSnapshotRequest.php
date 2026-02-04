<?php

namespace App\Client\Resources\DatabaseSnapshots;

use App\Client\Resources\Concerns\AcceptsInclude;
use App\Dto\DatabaseSnapshot;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;

class GetDatabaseSnapshotRequest extends Request
{
    use AcceptsInclude;

    protected Method $method = Method::GET;

    public function __construct(
        protected string $clusterId,
        protected string $snapshotId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/databases/clusters/{$this->clusterId}/snapshots/{$this->snapshotId}";
    }

    public function createDtoFromResponse(Response $response): DatabaseSnapshot
    {
        return DatabaseSnapshot::createFromResponse($response->json());
    }
}
