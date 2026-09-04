<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateDatabaseSnapshotRequestData;
use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;
use App\Dto\DatabaseSnapshot;
use Saloon\PaginationPlugin\Paginator;

class DatabaseSnapshotsResource extends Resource
{
    public function list(string $clusterId): Paginator
    {
        $request = new ListDatabaseSnapshotsRequest($clusterId);

        return $this->paginate($request);
    }

    public function get(string $snapshotId): DatabaseSnapshot
    {
        $request = new GetDatabaseSnapshotRequest(
            snapshotId: $snapshotId,
        );
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateDatabaseSnapshotRequestData $data): DatabaseSnapshot
    {
        $request = new CreateDatabaseSnapshotRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $snapshotId): void
    {
        $this->send(new DeleteDatabaseSnapshotRequest(
            snapshotId: $snapshotId,
        ));
    }
}
