<?php

// API Contract Tests
//
// Validates that every Saloon request class in app/Client/Resources/ uses the
// correct HTTP method and endpoint pattern as defined by the official Laravel
// Cloud API spec (https://cloud.laravel.com/docs/api).
//
// This catches regressions like the environment-variable-delete bug where the
// wrong HTTP method and URL were used. Each request class is checked via
// reflection (for the $method property) and source-code parsing (for the
// resolveEndpoint() return value) so we never need to instantiate the classes
// or their complex data-object dependencies.

use App\Client\Resources\Applications\CreateApplicationRequest;
use App\Client\Resources\Applications\DeleteApplicationRequest;
use App\Client\Resources\Applications\GetApplicationRequest;
use App\Client\Resources\Applications\ListApplicationsRequest;
use App\Client\Resources\Applications\UpdateApplicationAvatarRequest;
use App\Client\Resources\Applications\UpdateApplicationRequest;
use App\Client\Resources\BackgroundProcesses\CreateBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\DeleteBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\GetBackgroundProcessRequest;
use App\Client\Resources\BackgroundProcesses\ListBackgroundProcessesRequest;
use App\Client\Resources\BackgroundProcesses\UpdateBackgroundProcessRequest;
use App\Client\Resources\BucketKeys\CreateBucketKeyRequest;
use App\Client\Resources\BucketKeys\DeleteBucketKeyRequest;
use App\Client\Resources\BucketKeys\GetBucketKeyRequest;
use App\Client\Resources\BucketKeys\ListBucketKeysRequest;
use App\Client\Resources\BucketKeys\UpdateBucketKeyRequest;
use App\Client\Resources\Caches\CreateCacheRequest;
use App\Client\Resources\Caches\DeleteCacheRequest;
use App\Client\Resources\Caches\GetCacheMetricsRequest;
use App\Client\Resources\Caches\GetCacheRequest;
use App\Client\Resources\Caches\ListCachesRequest;
use App\Client\Resources\Caches\ListCacheTypesRequest;
use App\Client\Resources\Caches\UpdateCacheRequest;
use App\Client\Resources\Commands\GetCommandRequest;
use App\Client\Resources\Commands\ListCommandsRequest;
use App\Client\Resources\Commands\RunCommandRequest;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\DeleteDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterMetricsRequest;
use App\Client\Resources\DatabaseClusters\GetDatabaseClusterRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseClustersRequest;
use App\Client\Resources\DatabaseClusters\ListDatabaseTypesRequest;
use App\Client\Resources\DatabaseClusters\UpdateDatabaseClusterRequest;
use App\Client\Resources\DatabaseRestores\CreateDatabaseRestoreRequest;
use App\Client\Resources\Databases\CreateDatabaseRequest;
use App\Client\Resources\Databases\DeleteDatabaseRequest;
use App\Client\Resources\Databases\GetDatabaseRequest;
use App\Client\Resources\Databases\ListDatabasesRequest;
use App\Client\Resources\DatabaseSnapshots\CreateDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\DeleteDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\GetDatabaseSnapshotRequest;
use App\Client\Resources\DatabaseSnapshots\ListDatabaseSnapshotsRequest;
use App\Client\Resources\DedicatedClusters\ListDedicatedClustersRequest;
use App\Client\Resources\Deployments\GetDeploymentLogsRequest;
use App\Client\Resources\Deployments\GetDeploymentRequest;
use App\Client\Resources\Deployments\InitiateDeploymentRequest;
use App\Client\Resources\Deployments\ListDeploymentsRequest;
use App\Client\Resources\Domains\CreateDomainRequest;
use App\Client\Resources\Domains\DeleteDomainRequest;
use App\Client\Resources\Domains\GetDomainRequest;
use App\Client\Resources\Domains\ListDomainsRequest;
use App\Client\Resources\Domains\UpdateDomainRequest;
use App\Client\Resources\Domains\VerifyDomainRequest;
use App\Client\Resources\Environments\AddEnvironmentVariablesRequest;
use App\Client\Resources\Environments\CreateEnvironmentRequest;
use App\Client\Resources\Environments\DeleteEnvironmentRequest;
use App\Client\Resources\Environments\DeleteEnvironmentVariablesRequest;
use App\Client\Resources\Environments\GetEnvironmentMetricsRequest;
use App\Client\Resources\Environments\GetEnvironmentRequest;
use App\Client\Resources\Environments\ListEnvironmentLogsRequest;
use App\Client\Resources\Environments\ListEnvironmentsRequest;
use App\Client\Resources\Environments\ReplaceEnvironmentVariablesRequest;
use App\Client\Resources\Environments\StartEnvironmentRequest;
use App\Client\Resources\Environments\StopEnvironmentRequest;
use App\Client\Resources\Environments\UpdateEnvironmentRequest;
use App\Client\Resources\Instances\CreateInstanceRequest;
use App\Client\Resources\Instances\DeleteInstanceRequest;
use App\Client\Resources\Instances\GetInstanceRequest;
use App\Client\Resources\Instances\ListInstanceSizesRequest;
use App\Client\Resources\Instances\ListInstancesRequest;
use App\Client\Resources\Instances\UpdateInstanceRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\Client\Resources\Meta\ListIpAddressesRequest;
use App\Client\Resources\Meta\ListRegionsRequest;
use App\Client\Resources\ObjectStorageBuckets\CreateObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\DeleteObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\GetObjectStorageBucketRequest;
use App\Client\Resources\ObjectStorageBuckets\ListObjectStorageBucketsRequest;
use App\Client\Resources\ObjectStorageBuckets\UpdateObjectStorageBucketRequest;
use App\Client\Resources\WebSocketApplications\CreateWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\DeleteWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationMetricsRequest;
use App\Client\Resources\WebSocketApplications\GetWebSocketApplicationRequest;
use App\Client\Resources\WebSocketApplications\ListWebSocketApplicationsRequest;
use App\Client\Resources\WebSocketApplications\UpdateWebSocketApplicationRequest;
use App\Client\Resources\WebSocketClusters\CreateWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\DeleteWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterMetricsRequest;
use App\Client\Resources\WebSocketClusters\GetWebSocketClusterRequest;
use App\Client\Resources\WebSocketClusters\ListWebSocketClustersRequest;
use App\Client\Resources\WebSocketClusters\UpdateWebSocketClusterRequest;
use Saloon\Enums\Method;

// ---------------------------------------------------------------------------
// Helper: extract the HTTP method enum from a request class via reflection
// ---------------------------------------------------------------------------
function getRequestMethod(string $class): string
{
    $reflection = new ReflectionClass($class);
    $property = $reflection->getProperty('method');

    return $property->getDefaultValue()->value;
}

// ---------------------------------------------------------------------------
// Helper: extract the endpoint pattern from the resolveEndpoint() source code
// ---------------------------------------------------------------------------
function getEndpointPattern(string $class): string
{
    $reflection = new ReflectionClass($class);
    $file = $reflection->getFileName();
    $source = file_get_contents($file);

    // Match the return statement inside resolveEndpoint()
    // Handles both single-quoted and double-quoted strings
    if (preg_match('/function\s+resolveEndpoint\(\).*?return\s+[\'"]([^\'"]+)[\'"]\s*;/s', $source, $matches)) {
        // Normalize interpolated variables like {$this->applicationId} to a
        // generic placeholder so we can compare against the spec pattern.
        return preg_replace('/\{\$this->(?:data->)?\w+\}/', '{id}', $matches[1]);
    }

    return '';
}

// ---------------------------------------------------------------------------
// Dataset: every request class mapped to [expected HTTP method, endpoint regex]
//
// The endpoint regex uses [^/]+ to match any dynamic segment. Grouped by
// resource type and ordered to match the API spec documentation.
// ---------------------------------------------------------------------------

dataset('api_contract', function () {
    return [
        // --- Applications ---
        'CreateApplicationRequest' => [
            CreateApplicationRequest::class,
            'POST',
            '#^/applications$#',
        ],
        'DeleteApplicationRequest' => [
            DeleteApplicationRequest::class,
            'DELETE',
            '#^/applications/[^/]+$#',
        ],
        'GetApplicationRequest' => [
            GetApplicationRequest::class,
            'GET',
            '#^/applications/[^/]+$#',
        ],
        'ListApplicationsRequest' => [
            ListApplicationsRequest::class,
            'GET',
            '#^/applications$#',
        ],
        'UpdateApplicationRequest' => [
            UpdateApplicationRequest::class,
            'PATCH',
            '#^/applications/[^/]+$#',
        ],
        'UpdateApplicationAvatarRequest' => [
            UpdateApplicationAvatarRequest::class,
            'POST',
            '#^/applications/[^/]+/avatar$#',
        ],

        // --- Background Processes ---
        'CreateBackgroundProcessRequest' => [
            CreateBackgroundProcessRequest::class,
            'POST',
            '#^/instances/[^/]+/background-processes$#',
        ],
        'DeleteBackgroundProcessRequest' => [
            DeleteBackgroundProcessRequest::class,
            'DELETE',
            '#^/background-processes/[^/]+$#',
        ],
        'GetBackgroundProcessRequest' => [
            GetBackgroundProcessRequest::class,
            'GET',
            '#^/background-processes/[^/]+$#',
        ],
        'ListBackgroundProcessesRequest' => [
            ListBackgroundProcessesRequest::class,
            'GET',
            '#^/instances/[^/]+/background-processes$#',
        ],
        'UpdateBackgroundProcessRequest' => [
            UpdateBackgroundProcessRequest::class,
            'PATCH',
            '#^/background-processes/[^/]+$#',
        ],

        // --- Object Storage Keys (Bucket Keys) ---
        'CreateBucketKeyRequest' => [
            CreateBucketKeyRequest::class,
            'POST',
            '#^/buckets/[^/]+/keys$#',
        ],
        'DeleteBucketKeyRequest' => [
            DeleteBucketKeyRequest::class,
            'DELETE',
            '#^/bucket-keys/[^/]+$#',
        ],
        'GetBucketKeyRequest' => [
            GetBucketKeyRequest::class,
            'GET',
            '#^/bucket-keys/[^/]+$#',
        ],
        'ListBucketKeysRequest' => [
            ListBucketKeysRequest::class,
            'GET',
            '#^/buckets/[^/]+/keys$#',
        ],
        'UpdateBucketKeyRequest' => [
            UpdateBucketKeyRequest::class,
            'PATCH',
            '#^/bucket-keys/[^/]+$#',
        ],

        // --- Caches ---
        'CreateCacheRequest' => [
            CreateCacheRequest::class,
            'POST',
            '#^/caches$#',
        ],
        'DeleteCacheRequest' => [
            DeleteCacheRequest::class,
            'DELETE',
            '#^/caches/[^/]+$#',
        ],
        'GetCacheRequest' => [
            GetCacheRequest::class,
            'GET',
            '#^/caches/[^/]+$#',
        ],
        'GetCacheMetricsRequest' => [
            GetCacheMetricsRequest::class,
            'GET',
            '#^/caches/[^/]+/metrics$#',
        ],
        'ListCacheTypesRequest' => [
            ListCacheTypesRequest::class,
            'GET',
            '#^/caches/types$#',
        ],
        'ListCachesRequest' => [
            ListCachesRequest::class,
            'GET',
            '#^/caches$#',
        ],
        'UpdateCacheRequest' => [
            UpdateCacheRequest::class,
            'PATCH',
            '#^/caches/[^/]+$#',
        ],

        // --- Commands ---
        'GetCommandRequest' => [
            GetCommandRequest::class,
            'GET',
            '#^/commands/[^/]+$#',
        ],
        'ListCommandsRequest' => [
            ListCommandsRequest::class,
            'GET',
            '#^/environments/[^/]+/commands$#',
        ],
        'RunCommandRequest' => [
            RunCommandRequest::class,
            'POST',
            '#^/environments/[^/]+/commands$#',
        ],

        // --- Database Clusters ---
        'CreateDatabaseClusterRequest' => [
            CreateDatabaseClusterRequest::class,
            'POST',
            '#^/databases/clusters$#',
        ],
        'DeleteDatabaseClusterRequest' => [
            DeleteDatabaseClusterRequest::class,
            'DELETE',
            '#^/databases/clusters/[^/]+$#',
        ],
        'GetDatabaseClusterRequest' => [
            GetDatabaseClusterRequest::class,
            'GET',
            '#^/databases/clusters/[^/]+$#',
        ],
        'GetDatabaseClusterMetricsRequest' => [
            GetDatabaseClusterMetricsRequest::class,
            'GET',
            '#^/databases/clusters/[^/]+/metrics$#',
        ],
        'ListDatabaseClustersRequest' => [
            ListDatabaseClustersRequest::class,
            'GET',
            '#^/databases/clusters$#',
        ],
        'ListDatabaseTypesRequest' => [
            ListDatabaseTypesRequest::class,
            'GET',
            '#^/databases/types$#',
        ],
        'UpdateDatabaseClusterRequest' => [
            UpdateDatabaseClusterRequest::class,
            'PATCH',
            '#^/databases/clusters/[^/]+$#',
        ],

        // --- Database Restores ---
        'CreateDatabaseRestoreRequest' => [
            CreateDatabaseRestoreRequest::class,
            'POST',
            '#^/databases/clusters/[^/]+/restores$#',
        ],

        // --- Database Snapshots ---
        'CreateDatabaseSnapshotRequest' => [
            CreateDatabaseSnapshotRequest::class,
            'POST',
            '#^/databases/clusters/[^/]+/snapshots$#',
        ],
        'DeleteDatabaseSnapshotRequest' => [
            DeleteDatabaseSnapshotRequest::class,
            'DELETE',
            '#^/databases/clusters/[^/]+/snapshots/[^/]+$#',
        ],
        'GetDatabaseSnapshotRequest' => [
            GetDatabaseSnapshotRequest::class,
            'GET',
            '#^/databases/clusters/[^/]+/snapshots/[^/]+$#',
        ],
        'ListDatabaseSnapshotsRequest' => [
            ListDatabaseSnapshotsRequest::class,
            'GET',
            '#^/databases/clusters/[^/]+/snapshots$#',
        ],

        // --- Databases (schemas) ---
        'CreateDatabaseRequest' => [
            CreateDatabaseRequest::class,
            'POST',
            '#^/databases/clusters/[^/]+/databases$#',
        ],
        'DeleteDatabaseRequest' => [
            DeleteDatabaseRequest::class,
            'DELETE',
            '#^/databases/clusters/[^/]+/databases/[^/]+$#',
        ],
        'GetDatabaseRequest' => [
            GetDatabaseRequest::class,
            'GET',
            '#^/databases/clusters/[^/]+/databases/[^/]+$#',
        ],
        'ListDatabasesRequest' => [
            ListDatabasesRequest::class,
            'GET',
            '#^/databases/clusters/[^/]+/databases$#',
        ],

        // --- Dedicated Clusters ---
        'ListDedicatedClustersRequest' => [
            ListDedicatedClustersRequest::class,
            'GET',
            '#^/dedicated-clusters$#',
        ],

        // --- Deployments ---
        'GetDeploymentRequest' => [
            GetDeploymentRequest::class,
            'GET',
            '#^/deployments/[^/]+$#',
        ],
        'GetDeploymentLogsRequest' => [
            GetDeploymentLogsRequest::class,
            'GET',
            '#^/deployments/[^/]+/logs$#',
        ],
        'InitiateDeploymentRequest' => [
            InitiateDeploymentRequest::class,
            'POST',
            '#^/environments/[^/]+/deployments$#',
        ],
        'ListDeploymentsRequest' => [
            ListDeploymentsRequest::class,
            'GET',
            '#^/environments/[^/]+/deployments$#',
        ],

        // --- Domains ---
        'CreateDomainRequest' => [
            CreateDomainRequest::class,
            'POST',
            '#^/environments/[^/]+/domains$#',
        ],
        'DeleteDomainRequest' => [
            DeleteDomainRequest::class,
            'DELETE',
            '#^/domains/[^/]+$#',
        ],
        'GetDomainRequest' => [
            GetDomainRequest::class,
            'GET',
            '#^/domains/[^/]+$#',
        ],
        'ListDomainsRequest' => [
            ListDomainsRequest::class,
            'GET',
            '#^/environments/[^/]+/domains$#',
        ],
        'UpdateDomainRequest' => [
            UpdateDomainRequest::class,
            'PATCH',
            '#^/domains/[^/]+$#',
        ],
        'VerifyDomainRequest' => [
            VerifyDomainRequest::class,
            'POST',
            '#^/domains/[^/]+/verify$#',
        ],

        // --- Environments ---
        'AddEnvironmentVariablesRequest' => [
            AddEnvironmentVariablesRequest::class,
            'POST',
            '#^/environments/[^/]+/variables$#',
        ],
        'DeleteEnvironmentVariablesRequest' => [
            DeleteEnvironmentVariablesRequest::class,
            'POST',
            '#^/environments/[^/]+/variables/delete$#',
        ],
        'CreateEnvironmentRequest' => [
            CreateEnvironmentRequest::class,
            'POST',
            '#^/applications/[^/]+/environments$#',
        ],
        'DeleteEnvironmentRequest' => [
            DeleteEnvironmentRequest::class,
            'DELETE',
            '#^/environments/[^/]+$#',
        ],
        'GetEnvironmentRequest' => [
            GetEnvironmentRequest::class,
            'GET',
            '#^/environments/[^/]+$#',
        ],
        'GetEnvironmentMetricsRequest' => [
            GetEnvironmentMetricsRequest::class,
            'GET',
            '#^/environments/[^/]+/metrics$#',
        ],
        'ListEnvironmentLogsRequest' => [
            ListEnvironmentLogsRequest::class,
            'GET',
            '#^/environments/[^/]+/logs$#',
        ],
        'ListEnvironmentsRequest' => [
            ListEnvironmentsRequest::class,
            'GET',
            '#^/applications/[^/]+/environments$#',
        ],
        'ReplaceEnvironmentVariablesRequest' => [
            ReplaceEnvironmentVariablesRequest::class,
            'PUT',
            '#^/environments/[^/]+/variables$#',
        ],
        'StartEnvironmentRequest' => [
            StartEnvironmentRequest::class,
            'POST',
            '#^/environments/[^/]+/start$#',
        ],
        'StopEnvironmentRequest' => [
            StopEnvironmentRequest::class,
            'POST',
            '#^/environments/[^/]+/stop$#',
        ],
        'UpdateEnvironmentRequest' => [
            UpdateEnvironmentRequest::class,
            'PATCH',
            '#^/environments/[^/]+$#',
        ],

        // --- Instances ---
        'CreateInstanceRequest' => [
            CreateInstanceRequest::class,
            'POST',
            '#^/environments/[^/]+/instances$#',
        ],
        'DeleteInstanceRequest' => [
            DeleteInstanceRequest::class,
            'DELETE',
            '#^/instances/[^/]+$#',
        ],
        'GetInstanceRequest' => [
            GetInstanceRequest::class,
            'GET',
            '#^/instances/[^/]+$#',
        ],
        'ListInstanceSizesRequest' => [
            ListInstanceSizesRequest::class,
            'GET',
            '#^/instances/sizes$#',
        ],
        'ListInstancesRequest' => [
            ListInstancesRequest::class,
            'GET',
            '#^/environments/[^/]+/instances$#',
        ],
        'UpdateInstanceRequest' => [
            UpdateInstanceRequest::class,
            'PATCH',
            '#^/instances/[^/]+$#',
        ],

        // --- Organization / Meta ---
        'GetOrganizationRequest' => [
            GetOrganizationRequest::class,
            'GET',
            '#^/meta/organization$#',
        ],
        'ListRegionsRequest' => [
            ListRegionsRequest::class,
            'GET',
            '#^/meta/regions$#',
        ],
        'ListIpAddressesRequest' => [
            ListIpAddressesRequest::class,
            'GET',
            '#^/ip$#',
        ],

        // --- Object Storage Buckets ---
        'CreateObjectStorageBucketRequest' => [
            CreateObjectStorageBucketRequest::class,
            'POST',
            '#^/buckets$#',
        ],
        'DeleteObjectStorageBucketRequest' => [
            DeleteObjectStorageBucketRequest::class,
            'DELETE',
            '#^/buckets/[^/]+$#',
        ],
        'GetObjectStorageBucketRequest' => [
            GetObjectStorageBucketRequest::class,
            'GET',
            '#^/buckets/[^/]+$#',
        ],
        'ListObjectStorageBucketsRequest' => [
            ListObjectStorageBucketsRequest::class,
            'GET',
            '#^/buckets$#',
        ],
        'UpdateObjectStorageBucketRequest' => [
            UpdateObjectStorageBucketRequest::class,
            'PATCH',
            '#^/buckets/[^/]+$#',
        ],

        // --- WebSocket Applications ---
        'CreateWebSocketApplicationRequest' => [
            CreateWebSocketApplicationRequest::class,
            'POST',
            '#^/websocket-servers/[^/]+/applications$#',
        ],
        'DeleteWebSocketApplicationRequest' => [
            DeleteWebSocketApplicationRequest::class,
            'DELETE',
            '#^/websocket-applications/[^/]+$#',
        ],
        'GetWebSocketApplicationRequest' => [
            GetWebSocketApplicationRequest::class,
            'GET',
            '#^/websocket-applications/[^/]+$#',
        ],
        'GetWebSocketApplicationMetricsRequest' => [
            GetWebSocketApplicationMetricsRequest::class,
            'GET',
            '#^/websocket-applications/[^/]+/metrics$#',
        ],
        'ListWebSocketApplicationsRequest' => [
            ListWebSocketApplicationsRequest::class,
            'GET',
            '#^/websocket-servers/[^/]+/applications$#',
        ],
        'UpdateWebSocketApplicationRequest' => [
            UpdateWebSocketApplicationRequest::class,
            'PATCH',
            '#^/websocket-applications/[^/]+$#',
        ],

        // --- WebSocket Clusters ---
        'CreateWebSocketClusterRequest' => [
            CreateWebSocketClusterRequest::class,
            'POST',
            '#^/websocket-servers$#',
        ],
        'DeleteWebSocketClusterRequest' => [
            DeleteWebSocketClusterRequest::class,
            'DELETE',
            '#^/websocket-servers/[^/]+$#',
        ],
        'GetWebSocketClusterRequest' => [
            GetWebSocketClusterRequest::class,
            'GET',
            '#^/websocket-servers/[^/]+$#',
        ],
        'GetWebSocketClusterMetricsRequest' => [
            GetWebSocketClusterMetricsRequest::class,
            'GET',
            '#^/websocket-servers/[^/]+/metrics$#',
        ],
        'ListWebSocketClustersRequest' => [
            ListWebSocketClustersRequest::class,
            'GET',
            '#^/websocket-servers$#',
        ],
        'UpdateWebSocketClusterRequest' => [
            UpdateWebSocketClusterRequest::class,
            'PATCH',
            '#^/websocket-servers/[^/]+$#',
        ],
    ];
});

// ---------------------------------------------------------------------------
// Test: HTTP method matches the Cloud API spec
// ---------------------------------------------------------------------------
it('has correct HTTP method for', function (
    string $class,
    string $expectedMethod,
    string $endpointPattern,
) {
    $actualMethod = getRequestMethod($class);

    expect($actualMethod)->toBe($expectedMethod, "Expected {$class} to use {$expectedMethod}, got {$actualMethod}");
})->with('api_contract');

// ---------------------------------------------------------------------------
// Test: endpoint pattern matches the Cloud API spec
// ---------------------------------------------------------------------------
it('has correct endpoint for', function (
    string $class,
    string $expectedMethod,
    string $endpointPattern,
) {
    $endpoint = getEndpointPattern($class);

    expect($endpoint)->toMatch($endpointPattern, "Expected endpoint of {$class} to match {$endpointPattern}, got {$endpoint}");
})->with('api_contract');
