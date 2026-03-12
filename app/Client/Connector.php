<?php

namespace App\Client;

use App\Client\Resources\ApplicationsResource;
use App\Client\Resources\BackgroundProcessesResource;
use App\Client\Resources\BucketKeysResource;
use App\Client\Resources\CachesResource;
use App\Client\Resources\CliAuthResource;
use App\Client\Resources\CommandsResource;
use App\Client\Resources\DatabaseClustersResource;
use App\Client\Resources\DatabaseRestoresResource;
use App\Client\Resources\DatabaseSnapshotsResource;
use App\Client\Resources\DatabasesResource;
use App\Client\Resources\DedicatedClustersResource;
use App\Client\Resources\DeploymentsResource;
use App\Client\Resources\DomainsResource;
use App\Client\Resources\EnvironmentsResource;
use App\Client\Resources\InstancesResource;
use App\Client\Resources\MetaResource;
use App\Client\Resources\ObjectStorageBucketsResource;
use App\Client\Resources\WebSocketApplicationsResource;
use App\Client\Resources\WebSocketClustersResource;
use App\Support\ContextDetector;
use Illuminate\Support\Facades\Cache;
use Saloon\CachePlugin\Contracts\Driver;
use Saloon\CachePlugin\Drivers\LaravelCacheDriver;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector as SaloonConnector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\HasPagination;
use Saloon\PaginationPlugin\PagedPaginator;
use Saloon\PaginationPlugin\Paginator as SaloonPaginator;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
use SensitiveParameter;

class Connector extends SaloonConnector implements HasPagination
{
    use AlwaysThrowOnErrors;

    public function resolveCacheDriver(): Driver
    {
        return new LaravelCacheDriver(Cache::store('array'));
    }

    public function cacheExpiryInSeconds(): int
    {
        return 60 * 60 * 24;
    }

    public function __construct(
        #[SensitiveParameter]
        protected string $apiToken,
    ) {
        //
    }

    public static function unauthenticated(): self
    {
        return new self('');
    }

    public function boot(PendingRequest $pendingRequest): void
    {
        if (! method_exists($pendingRequest->getRequest(), 'getInclude')) {
            return;
        }

        if ($include = $pendingRequest->getRequest()->getInclude()) {
            $pendingRequest->query()->add('include', $include);
        }
    }

    public function resolveBaseUrl(): string
    {
        return config('app.base_url').'/api';
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->apiToken);
    }

    protected function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
            'X-Cloud-Cli-Version' => config('app.version'),
        ];

        if ($terminal = ContextDetector::terminal()) {
            $headers['X-Cloud-Cli-Terminal'] = $terminal;
        }

        if ($agent = ContextDetector::agent()) {
            $headers['X-Cloud-Cli-Agent'] = $agent;
        }

        return $headers;
    }

    public function applications(): ApplicationsResource
    {
        return new ApplicationsResource($this);
    }

    public function environments(): EnvironmentsResource
    {
        return new EnvironmentsResource($this);
    }

    public function deployments(): DeploymentsResource
    {
        return new DeploymentsResource($this);
    }

    public function domains(): DomainsResource
    {
        return new DomainsResource($this);
    }

    public function instances(): InstancesResource
    {
        return new InstancesResource($this);
    }

    public function commands(): CommandsResource
    {
        return new CommandsResource($this);
    }

    public function backgroundProcesses(): BackgroundProcessesResource
    {
        return new BackgroundProcessesResource($this);
    }

    public function databaseClusters(): DatabaseClustersResource
    {
        return new DatabaseClustersResource($this);
    }

    public function databases(): DatabasesResource
    {
        return new DatabasesResource($this);
    }

    public function databaseSnapshots(): DatabaseSnapshotsResource
    {
        return new DatabaseSnapshotsResource($this);
    }

    public function databaseRestores(): DatabaseRestoresResource
    {
        return new DatabaseRestoresResource($this);
    }

    public function objectStorageBuckets(): ObjectStorageBucketsResource
    {
        return new ObjectStorageBucketsResource($this);
    }

    public function bucketKeys(): BucketKeysResource
    {
        return new BucketKeysResource($this);
    }

    public function caches(): CachesResource
    {
        return new CachesResource($this);
    }

    public function websocketClusters(): WebSocketClustersResource
    {
        return new WebSocketClustersResource($this);
    }

    public function websocketApplications(): WebSocketApplicationsResource
    {
        return new WebSocketApplicationsResource($this);
    }

    public function meta(): MetaResource
    {
        return new MetaResource($this);
    }

    public function dedicatedClusters(): DedicatedClustersResource
    {
        return new DedicatedClustersResource($this);
    }

    public function cliAuth(): CliAuthResource
    {
        return new CliAuthResource(self::unauthenticated());
    }

    public function paginate(Request $request): SaloonPaginator
    {
        return new class(connector: $this, request: $request) extends PagedPaginator
        {
            protected bool $detectInfiniteLoop = false;

            protected function isLastPage(Response $response): bool
            {
                return is_null($response->json('links.next'));
            }

            protected function getPageItems(Response $response, Request $request): array
            {
                return $request->createDtoFromResponse($response);
            }
        };
    }
}
