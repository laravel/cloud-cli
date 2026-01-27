<?php

namespace App\Client\Resources;

use App\Client\Connector;
use App\Client\Resources\Environments\AddEnvironmentVariablesRequest;
use App\Client\Resources\Environments\CreateEnvironmentRequest;
use App\Client\Resources\Environments\DeleteEnvironmentRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentLogsRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\ReplaceEnvironmentVariablesRequest;
use App\Client\Resources\Environments\StartEnvironmentRequest;
use App\Client\Resources\Environments\StopEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\ResponseMapper;
use App\Dto\Environment;
use App\Dto\EnvironmentLog;
use App\Dto\Paginated;

class EnvironmentsResource
{
    public function __construct(
        protected Connector $connector,
    ) {
        //
    }

    public function list(string $applicationId): Paginated
    {
        $response = $this->connector->send(new ListEnvironmentsRequest($applicationId));

        return ResponseMapper::mapPaginated($response->json(), fn ($response, $item) => ResponseMapper::mapEnvironment($response, $item));
    }

    public function get(string $environmentId, ?string $include = null): Environment
    {
        $response = $this->connector->send(new GetEnvironmentRequest(
            environmentId: $environmentId,
            include: $include,
        ));

        return ResponseMapper::mapEnvironment($response->json());
    }

    public function create(string $applicationId, string $name, ?string $branch = null): Environment
    {
        $response = $this->connector->send(new CreateEnvironmentRequest(
            applicationId: $applicationId,
            name: $name,
            branch: $branch,
        ));

        return ResponseMapper::mapEnvironment($response->json());
    }

    public function update(string $environmentId, array $data): Environment
    {
        $response = $this->connector->send(new UpdateEnvironmentRequest(
            environmentId: $environmentId,
            data: $data,
        ));

        return ResponseMapper::mapEnvironment($response->json());
    }

    public function delete(string $environmentId): void
    {
        $this->connector->send(new DeleteEnvironmentRequest($environmentId));
    }

    public function logs(string $environmentId, string $from, string $to, ?string $cursor = null, ?string $type = null, ?string $query = null): Paginated
    {
        $response = $this->connector->send(new ListEnvironmentLogsRequest(
            environmentId: $environmentId,
            from: $from,
            to: $to,
            cursor: $cursor,
            type: $type,
            query: $query,
        ));

        $responseData = $response->json();
        $logs = collect($responseData['data'] ?? [])->map(fn ($item) => new EnvironmentLog(
            message: $item['message'] ?? '',
            level: \App\Enums\LogLevel::from($item['level'] ?? 'info'),
            type: \App\Enums\LogType::from($item['type'] ?? 'application'),
            loggedAt: isset($item['logged_at']) ? \Carbon\CarbonImmutable::parse($item['logged_at']) : \Carbon\CarbonImmutable::now(),
            data: $item['data'] ?? null,
        ))->toArray();

        $meta = $responseData['meta'] ?? [];
        $links = [];
        if (isset($meta['cursor'])) {
            $links['next'] = $meta['cursor'];
        }

        return new Paginated(data: $logs, links: $links);
    }

    public function addVariables(string $environmentId, array $variables): void
    {
        $this->connector->send(new AddEnvironmentVariablesRequest(
            environmentId: $environmentId,
            variables: $variables,
        ));
    }

    public function replaceVariables(string $environmentId, array $variables): void
    {
        $this->connector->send(new ReplaceEnvironmentVariablesRequest(
            environmentId: $environmentId,
            variables: $variables,
        ));
    }

    public function start(string $environmentId): void
    {
        $this->connector->send(new StartEnvironmentRequest($environmentId));
    }

    public function stop(string $environmentId): void
    {
        $this->connector->send(new StopEnvironmentRequest($environmentId));
    }
}
