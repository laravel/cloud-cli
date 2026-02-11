<?php

namespace App\Client\Resources;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Client\Requests\CreateEnvironmentRequestData;
use App\Client\Requests\ReplaceEnvironmentVariablesRequestData;
use App\Client\Requests\StartEnvironmentRequestData;
use App\Client\Requests\StopEnvironmentRequestData;
use App\Client\Requests\UpdateEnvironmentRequestData;
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

    public function create(CreateEnvironmentRequestData $data): Environment
    {
        $request = new CreateEnvironmentRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateEnvironmentRequestData $data): Environment
    {
        $request = new UpdateEnvironmentRequest($data);
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

    public function addVariables(AddEnvironmentVariablesRequestData $data): void
    {
        $this->send(new AddEnvironmentVariablesRequest($data));
    }

    public function replaceVariables(string $environmentId, array $variables = [], ?string $content = null): void
    {
        $this->send(new ReplaceEnvironmentVariablesRequest(new ReplaceEnvironmentVariablesRequestData(
            environmentId: $environmentId,
            content: $content,
            variables: $variables,
        )));
    }

    public function start(string $environmentId): void
    {
        $this->send(new StartEnvironmentRequest(new StartEnvironmentRequestData($environmentId)));
    }

    public function stop(string $environmentId): void
    {
        $this->send(new StopEnvironmentRequest(new StopEnvironmentRequestData($environmentId)));
    }
}
