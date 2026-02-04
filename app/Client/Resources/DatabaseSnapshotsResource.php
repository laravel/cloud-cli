<?php

namespace App\Client\Resources;

use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;
use App\Dto\DatabaseSnapshot;

class DatabaseSnapshotsResource extends Resource
{
    /**
     * @return array<int, DatabaseSnapshot>
     */
    public function list(string $clusterId): array
    {
        $response = $this->send(new ListDatabaseSnapshotsRequest($clusterId));
        $data = $response->json()['data'] ?? [];

        return collect($data)->map(fn (array $item) => DatabaseSnapshot::createFromResponse(['data' => $item]))->all();
    }

    public function get(string $clusterId, string $snapshotId): DatabaseSnapshot
    {
        $request = new GetDatabaseSnapshotRequest(
            clusterId: $clusterId,
            snapshotId: $snapshotId,
        );
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $clusterId): DatabaseSnapshot
    {
        $response = $this->send(new CreateDatabaseSnapshotRequest($clusterId));
        $data = $response->json()['data'] ?? [];

        return DatabaseSnapshot::createFromResponse(['data' => $data]);
    }

    public function delete(string $clusterId, string $snapshotId): void
    {
        $this->send(new DeleteDatabaseSnapshotRequest(
            clusterId: $clusterId,
            snapshotId: $snapshotId,
        ));
    }
}
