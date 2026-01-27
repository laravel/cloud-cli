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

        return $this->connector->paginate($request)->transform(function ($response) {
            $responseData = $response->json();

            return collect($responseData['data'] ?? [])->map(fn ($item) => Database::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]))->toArray();
        });
    }

    public function get(string $clusterId, string $databaseId): Database
    {
        $response = $this->connector->send(new GetDatabaseRequest(
            clusterId: $clusterId,
            databaseId: $databaseId,
        ));

        return Database::fromJsonApi($response->json());
    }

    public function create(string $clusterId, string $name): Database
    {
        $response = $this->connector->send(new CreateDatabaseRequest(
            clusterId: $clusterId,
            name: $name,
        ));

        return Database::fromJsonApi($response->json());
    }

    public function delete(string $clusterId, string $databaseId): void
    {
        $this->connector->send(new DeleteDatabaseRequest(
            clusterId: $clusterId,
            databaseId: $databaseId,
        ));
    }
}
