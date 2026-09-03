<?php

namespace App\Client\Resources\DatabaseSnapshots;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteDatabaseSnapshotRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        protected string $snapshotId,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/database-snapshots/{$this->snapshotId}";
    }
}
