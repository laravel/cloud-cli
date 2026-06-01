<?php

namespace App\Client\Resources;

use App\Client\Requests\CreateInstanceRequestData;
use App\Client\Requests\UpdateInstanceRequestData;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\DeleteInstanceRequest;
use App\Client\Resources\Instances\DeleteManagedQueueFailedJobRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Instances\ListManagedQueueFailedJobsRequest;
use App\Client\Resources\Instances\PauseManagedQueueRequest;
use App\Client\Resources\Instances\PurgeManagedQueueRequest;
use App\Client\Resources\Instances\ResumeManagedQueueRequest;
use App\Client\Resources\Instances\RetryManagedQueueFailedJobRequest;
use App\Client\Resources\Instances\SetDefaultManagedQueueRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Dto\EnvironmentInstance;
use App\Dto\InstanceSizes;
use Saloon\PaginationPlugin\Paginator;

class InstancesResource extends Resource
{
    public function list(string $environmentId): Paginator
    {
        $request = new ListInstancesRequest($environmentId);

        return $this->paginate($request);
    }

    public function get(string $instanceId): EnvironmentInstance
    {
        $request = new GetInstanceRequest($instanceId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function create(CreateInstanceRequestData $data): EnvironmentInstance
    {
        $request = new CreateInstanceRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function update(UpdateInstanceRequestData $data): EnvironmentInstance
    {
        $request = new UpdateInstanceRequest($data);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function delete(string $instanceId): void
    {
        $this->send(new DeleteInstanceRequest($instanceId));
    }

    public function sizes(): InstanceSizes
    {
        $request = new ListInstanceSizesRequest;
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function pause(string $instanceId): EnvironmentInstance
    {
        $request = new PauseManagedQueueRequest($instanceId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function resume(string $instanceId): EnvironmentInstance
    {
        $request = new ResumeManagedQueueRequest($instanceId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function purge(string $instanceId): EnvironmentInstance
    {
        $request = new PurgeManagedQueueRequest($instanceId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function setDefault(string $instanceId): EnvironmentInstance
    {
        $request = new SetDefaultManagedQueueRequest($instanceId);
        $response = $this->send($request);

        return $request->createDtoFromResponse($response);
    }

    public function failedJobs(string $instanceId): Paginator
    {
        return $this->paginate(new ListManagedQueueFailedJobsRequest($instanceId));
    }

    public function deleteFailedJob(string $instanceId, string $jobId): void
    {
        $this->send(new DeleteManagedQueueFailedJobRequest($instanceId, $jobId));
    }

    public function retryFailedJob(string $instanceId, string $jobId): void
    {
        $this->send(new RetryManagedQueueFailedJobRequest($instanceId, $jobId));
    }
}
