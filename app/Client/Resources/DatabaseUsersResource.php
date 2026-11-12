<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateDatabaseUserRequestData;
use App\Client\Requests\UpdateDatabaseUserRequestData;
use App\Client\Resources\DatabaseUsers\CreateDatabaseUserRequest;
use App\Client\Resources\DatabaseUsers\DeleteDatabaseUserRequest;
use App\Client\Resources\DatabaseUsers\GetDatabaseUserRequest;
use App\Client\Resources\DatabaseUsers\ListDatabaseUsersRequest;
use App\Client\Resources\DatabaseUsers\UpdateDatabaseUserRequest;
use App\Dto\DatabaseUser;
use Saloon\PaginationPlugin\Paginator;

class DatabaseUsersResource extends Resource
{
    public function list(string $clusterId): Paginator
    {
        $request = new ListDatabaseUsersRequest($clusterId);

        return $this->paginate($request);
    }

    public function get(string $clusterId, string $userId): DatabaseUser
    {
        $request = new GetDatabaseUserRequest(
            clusterId: $clusterId,
            userId: $userId,
        );
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateDatabaseUserRequestData $data): DatabaseUser
    {
        $request = new CreateDatabaseUserRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateDatabaseUserRequestData $data): DatabaseUser
    {
        $request = new UpdateDatabaseUserRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $clusterId, string $userId): void
    {
        $this->send(new DeleteDatabaseUserRequest(
            clusterId: $clusterId,
            userId: $userId,
        ));
    }

    public function withDefaultIncludes(): static
    {
        return $this->include('databases');
    }
}
