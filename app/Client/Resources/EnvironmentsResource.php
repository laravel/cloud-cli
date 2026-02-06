<?php

namespace App\Client\Resources;

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
use App\Dto\Environment;
use Saloon\PaginationPlugin\Paginator;

class EnvironmentsResource extends Resource
{
    public function list(string $applicationId): Paginator
    {
        $request = new ListEnvironmentsRequest($applicationId);

        return $this->paginate($request);
    }

    public function get(string $environmentId): Environment
    {
        $request = new GetEnvironmentRequest($environmentId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(string $applicationId, string $name, ?string $branch = null): Environment
    {
        $request = new CreateEnvironmentRequest(
            applicationId: $applicationId,
            name: $name,
            branch: $branch,
        );

        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(string $environmentId, array $data): Environment
    {
        $request = new UpdateEnvironmentRequest(
            environmentId: $environmentId,
            data: $data,
        );

        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $environmentId): void
    {
        $this->send(new DeleteEnvironmentRequest($environmentId));
    }

    public function logs(string $environmentId, string $from, string $to, ?string $cursor = null, ?string $type = null, ?string $query = null): array
    {
        $request = new ListEnvironmentLogsRequest(
            environmentId: $environmentId,
            from: $from,
            to: $to,
            cursor: $cursor,
            type: $type,
            queryString: $query,
        );

        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    /**
     * @param  'append'|'set'  $method
     */
    public function addVariables(string $environmentId, array $variables, string $method = 'append'): void
    {
        $this->send(new AddEnvironmentVariablesRequest(
            environmentId: $environmentId,
            variables: $variables,
            action: $method,
        ));
    }

    public function replaceVariables(string $environmentId, array $variables = [], ?string $content = null): void
    {
        $this->send(new ReplaceEnvironmentVariablesRequest(
            environmentId: $environmentId,
            content: $content,
            variables: $variables,
        ));
    }

    public function start(string $environmentId): void
    {
        $this->send(new StartEnvironmentRequest($environmentId));
    }

    public function stop(string $environmentId): void
    {
        $this->send(new StopEnvironmentRequest($environmentId));
    }
}
