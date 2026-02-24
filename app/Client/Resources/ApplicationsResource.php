<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateApplicationRequestData;
use App\Client\Requests\UpdateApplicationAvatarRequestData;
use App\Client\Requests\UpdateApplicationRequestData;
use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\DeleteApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Applications\UpdateApplicationAvatarRequest;
use App\Client\Resources\Applications\UpdateApplicationRequest;
use App\Dto\Application;
use Saloon\PaginationPlugin\Paginator;

class ApplicationsResource extends Resource
{
    public function list(?string $name = null, ?string $region = null, ?string $slug = null): Paginator
    {
        $request = new ListApplicationsRequest(
            name: $name,
            region: $region,
            slug: $slug,
        );

        return $this->paginate($request);
    }

    public function get(string $applicationId): Application
    {
        $request = new GetApplicationRequest($applicationId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateApplicationRequestData $data): Application
    {
        $request = new CreateApplicationRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateApplicationRequestData $data): Application
    {
        $request = new UpdateApplicationRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function updateAvatar(UpdateApplicationAvatarRequestData $data): Application
    {
        $request = new UpdateApplicationAvatarRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $applicationId): void
    {
        $this->send(new DeleteApplicationRequest($applicationId));
    }

    public function withDefaultIncludes(): static
    {
        return $this->include('organization', 'environments', 'defaultEnvironment');
    }
}
