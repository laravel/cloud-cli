<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Databases\CreateDatabaseRequest;
use App\Client\Resources\Databases\DeleteDatabaseRequest;
use App\Client\Resources\Databases\GetDatabaseRequest;
use App\Client\Resources\Databases\ListDatabasesRequest;
use App\Dto\Database;
use Saloon\PaginationPlugin\Paginator;

class DatabasesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $clusterId): Paginator
    {
        $request = new ListDatabasesRequest($clusterId);

        return $this->connector->paginate($request);
    }

    public function get(string $clusterId, string $databaseId): Database
    {
        $request = new GetDatabaseRequest(
            clusterId: $clusterId,
            databaseId: $databaseId,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $clusterId, string $name): Database
    {
        $request = new CreateDatabaseRequest(
            clusterId: $clusterId,
            name: $name,
        );

        $response = $this->connector->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId, string $databaseId): void
    {
        $this->connector->send(new DeleteDatabaseRequest(
            clusterId: $clusterId,
            databaseId: $databaseId,
        ));
    }
}
