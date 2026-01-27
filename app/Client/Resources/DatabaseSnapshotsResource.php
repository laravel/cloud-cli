<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;

class DatabaseSnapshotsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $clusterId): array
    {
        $response = $this->connector->send(new ListDatabaseSnapshotsRequest($clusterId));

        return $response->json()['data'] ?? [];
    }

    public function get(string $clusterId, string $snapshotId): array
    {
        $response = $this->connector->send(new GetDatabaseSnapshotRequest(
            clusterId: $clusterId,
            snapshotId: $snapshotId,
        ));

        return $response->json()['data'] ?? [];
    }

    public function create(string $clusterId): array
    {
        $response = $this->connector->send(new CreateDatabaseSnapshotRequest($clusterId));

        return $response->json()['data'] ?? [];
    }

    public function delete(string $clusterId, string $snapshotId): void
    {
        $this->connector->send(new DeleteDatabaseSnapshotRequest(
            clusterId: $clusterId,
            snapshotId: $snapshotId,
        ));
    }
}
