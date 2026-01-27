<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Databases\CreateDatabaseRequest;
use App\Client\Resources\Databases\DeleteDatabaseRequest;
use App\Client\Resources\Databases\GetDatabaseRequest;
use App\Client\Resources\Databases\ListDatabasesRequest;
use App\Client\ResponseMapper;
use App\Dto\Database;
use App\Dto\Paginated;

class DatabasesResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $clusterId): Paginated
    {
        $response = $this->connector->send(new ListDatabasesRequest($clusterId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapDatabase($response, $item));
    }

    public function get(string $clusterId, string $databaseId): Database
    {
        $response = $this->connector->send(new GetDatabaseRequest(
            clusterId: $clusterId,
            databaseId: $databaseId,
        ));

        return ResponseMapper::mapDatabase($response->json());
    }

    public function create(string $clusterId, string $name): Database
    {
        $response = $this->connector->send(new CreateDatabaseRequest(
            clusterId: $clusterId,
            name: $name,
        ));

        return ResponseMapper::mapDatabase($response->json());
    }

    public function delete(string $clusterId, string $databaseId): void
    {
        $this->connector->send(new DeleteDatabaseRequest(
            clusterId: $clusterId,
            databaseId: $databaseId,
        ));
    }
}
