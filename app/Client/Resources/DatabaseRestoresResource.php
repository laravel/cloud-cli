<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\DatabaseRestores\CreateDatabaseRestoreRequest;
use App\Dto\DatabaseCluster;

class DatabaseRestoresResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function create(string $clusterId, ?string $snapshotId = null, ?string $pointInTime = null): DatabaseCluster
    {
        $response = $this->connector->send(new CreateDatabaseRestoreRequest(
            clusterId: $clusterId,
            snapshotId: $snapshotId,
            pointInTime: $pointInTime,
        ));

        return DatabaseCluster::fromJsonApi($response->json());
    }
}
