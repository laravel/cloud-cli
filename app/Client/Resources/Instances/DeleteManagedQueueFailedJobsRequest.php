<?php

namespace App\Client\Resources\Instances;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteManagedQueueFailedJobsRequest extends Request
{
    protected Method $method = Method::DELETE;

    /**
     * @param  list<string>  $jobIds
     */
    public function __construct(
        protected string $instanceId,
        protected array $jobIds = [],
        protected ?string $failedBefore = null,
    ) {
        //
    }

    public function resolveEndpoint(): string
    {
        return "/instances/{$this->instanceId}/failed-jobs";
    }

    protected function defaultQuery(): array
    {
        $query = [];

        if ($this->jobIds !== []) {
            $query['jobIds'] = implode(',', $this->jobIds);
        }

        if ($this->failedBefore !== null) {
            $query['failedBefore'] = $this->failedBefore;
        }

        return $query;
    }
}
