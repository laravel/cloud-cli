<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Applications\UpdateApplicationRequest;
use App\Dto\Application;
use Saloon\PaginationPlugin\Paginator;

class ApplicationsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(?string $include = null, ?string $name = null, ?string $region = null, ?string $slug = null): Paginator
    {
        $request = new ListApplicationsRequest(
            include: $include,
            name: $name,
            region: $region,
            slug: $slug,
        );

        return $this->connector->paginate($request)->transform(
            fn ($responseData, $item) => Application::fromJsonApi(['data' => $item, 'included' => $responseData['included'] ?? []]),
        );
    }

    public function get(string $applicationId, ?string $include = null): Application
    {
        $response = $this->connector->send(new GetApplicationRequest(
            applicationId: $applicationId,
            include: $include,
        ));

        return Application::fromJsonApi($response->json());
    }

    public function create(string $repository, string $name, string $region): Application
    {
        $response = $this->connector->send(new CreateApplicationRequest(
            repository: $repository,
            name: $name,
            region: $region,
        ));

        return Application::fromJsonApi($response->json());
    }

    public function update(string $applicationId, array $data): Application
    {
        $avatar = null;
        if (isset($data['avatar']) && is_array($data['avatar']) && count($data['avatar']) === 2) {
            [$avatarContent, $extension] = $data['avatar'];
            $avatar = new \Saloon\Data\MultipartValue(
                value: $avatarContent,
                filename: 'avatar.'.$extension,
            );
            unset($data['avatar']);
        }

        $response = $this->connector->send(new UpdateApplicationRequest(
            applicationId: $applicationId,
            name: $data['name'] ?? null,
            slug: $data['slug'] ?? null,
            defaultEnvironmentId: $data['default_environment_id'] ?? null,
            repository: $data['repository'] ?? null,
            slackChannel: $data['slack_channel'] ?? null,
            avatar: $avatar,
        ));

        return Application::fromJsonApi($response->json());
    }
}
