# Laravel Cloud API SDK

A fully featured Saloon SDK for the Laravel Cloud API.

## Installation

The SDK is included in this project. Make sure Saloon is installed:

```bash
composer require saloonphp/saloon
```

## Usage

### Basic Example

```php
use App\Client\Connector;
use App\Client\Resources\Applications\ListApplicationsRequest;

$connector = new Connector('your-api-token');
$response = $connector->send(new ListApplicationsRequest());

$data = $response->json();
```

### Available Resources

#### Applications
- `ListApplicationsRequest` - List all applications
- `GetApplicationRequest` - Get a specific application
- `CreateApplicationRequest` - Create a new application
- `UpdateApplicationRequest` - Update an application

#### Environments
- `ListEnvironmentsRequest` - List environments for an application
- `GetEnvironmentRequest` - Get a specific environment
- `CreateEnvironmentRequest` - Create a new environment
- `UpdateEnvironmentRequest` - Update an environment
- `DeleteEnvironmentRequest` - Delete an environment
- `ListEnvironmentLogsRequest` - List environment logs
- `AddEnvironmentVariablesRequest` - Add environment variables
- `DeleteEnvironmentVariablesRequest` - Delete environment variables by key
- `StartEnvironmentRequest` - Start an environment
- `StopEnvironmentRequest` - Stop an environment

#### Deployments
- `ListDeploymentsRequest` - List deployments for an environment
- `GetDeploymentRequest` - Get a specific deployment
- `InitiateDeploymentRequest` - Initiate a new deployment

#### Domains
- `ListDomainsRequest` - List domains for an environment
- `GetDomainRequest` - Get a specific domain
- `CreateDomainRequest` - Create a new domain
- `UpdateDomainRequest` - Update a domain
- `DeleteDomainRequest` - Delete a domain
- `VerifyDomainRequest` - Verify a domain

#### Instances
- `ListInstancesRequest` - List instances for an environment
- `GetInstanceRequest` - Get a specific instance
- `CreateInstanceRequest` - Create a new instance
- `UpdateInstanceRequest` - Update an instance
- `DeleteInstanceRequest` - Delete an instance
- `ListInstanceSizesRequest` - List available instance sizes

#### Commands
- `ListCommandsRequest` - List commands for an environment
- `GetCommandRequest` - Get a specific command
- `RunCommandRequest` - Run a command on an environment

#### Background Processes
- `ListBackgroundProcessesRequest` - List background processes for an instance
- `GetBackgroundProcessRequest` - Get a specific background process
- `CreateBackgroundProcessRequest` - Create a new background process
- `UpdateBackgroundProcessRequest` - Update a background process
- `DeleteBackgroundProcessRequest` - Delete a background process

#### Database Clusters
- `ListDatabaseClustersRequest` - List all database clusters
- `GetDatabaseClusterRequest` - Get a specific database cluster
- `CreateDatabaseClusterRequest` - Create a new database cluster
- `UpdateDatabaseClusterRequest` - Update a database cluster
- `DeleteDatabaseClusterRequest` - Delete a database cluster
- `ListDatabaseTypesRequest` - List available database types

#### Databases (within clusters)
- `ListDatabasesRequest` - List databases in a cluster
- `GetDatabaseRequest` - Get a specific database
- `CreateDatabaseRequest` - Create a new database
- `DeleteDatabaseRequest` - Delete a database

#### Database Snapshots
- `ListDatabaseSnapshotsRequest` - List snapshots for a cluster
- `GetDatabaseSnapshotRequest` - Get a specific snapshot
- `CreateDatabaseSnapshotRequest` - Create a new snapshot
- `DeleteDatabaseSnapshotRequest` - Delete a snapshot

#### Database Restores
- `CreateDatabaseRestoreRequest` - Restore a database from a snapshot or point in time

#### Object Storage Buckets
- `ListObjectStorageBucketsRequest` - List all object storage buckets
- `GetObjectStorageBucketRequest` - Get a specific bucket
- `CreateObjectStorageBucketRequest` - Create a new bucket
- `UpdateObjectStorageBucketRequest` - Update a bucket
- `DeleteObjectStorageBucketRequest` - Delete a bucket

#### Bucket Keys
- `ListBucketKeysRequest` - List keys for a bucket
- `GetBucketKeyRequest` - Get a specific key
- `CreateBucketKeyRequest` - Create a new key
- `UpdateBucketKeyRequest` - Update a key
- `DeleteBucketKeyRequest` - Delete a key

#### Caches
- `ListCachesRequest` - List all caches
- `GetCacheRequest` - Get a specific cache
- `CreateCacheRequest` - Create a new cache
- `UpdateCacheRequest` - Update a cache
- `DeleteCacheRequest` - Delete a cache
- `ListCacheTypesRequest` - List available cache types

#### WebSocket Clusters
- `ListWebSocketClustersRequest` - List all WebSocket clusters
- `GetWebSocketClusterRequest` - Get a specific cluster
- `CreateWebSocketClusterRequest` - Create a new cluster
- `UpdateWebSocketClusterRequest` - Update a cluster
- `DeleteWebSocketClusterRequest` - Delete a cluster

#### WebSocket Applications
- `ListWebSocketApplicationsRequest` - List applications for a cluster
- `GetWebSocketApplicationRequest` - Get a specific application
- `CreateWebSocketApplicationRequest` - Create a new application
- `UpdateWebSocketApplicationRequest` - Update an application
- `DeleteWebSocketApplicationRequest` - Delete an application

#### Meta
- `GetOrganizationRequest` - Get the authenticated organization
- `ListRegionsRequest` - List available regions
- `ListIpAddressesRequest` - List IP addresses to whitelist

#### Dedicated Clusters
- `ListDedicatedClustersRequest` - List all dedicated clusters

## Examples

### List Applications

```php
use App\Client\Connector;
use App\Client\Resources\Applications\ListApplicationsRequest;

$connector = new Connector('your-api-token');
$response = $connector->send(new ListApplicationsRequest(
    include: 'organization,environments'
));

$applications = $response->json()['data'];
```

### Create an Application

```php
use App\Client\Connector;
use App\Client\Requests\CreateApplicationRequestData;
use App\Client\Resources\Applications\CreateApplicationRequest;

$connector = new Connector('your-api-token');
$response = $connector->send(new CreateApplicationRequest(new CreateApplicationRequestData(
    repository: 'username/repo',
    name: 'My App',
    region: 'us-east-1'
)));

$application = $response->json()['data'];
```

### Create an Environment

```php
use App\Client\Connector;
use App\Client\Requests\CreateEnvironmentRequestData;
use App\Client\Resources\Environments\CreateEnvironmentRequest;

$connector = new Connector('your-api-token');
$response = $connector->send(new CreateEnvironmentRequest(new CreateEnvironmentRequestData(
    applicationId: 'app-123',
    name: 'production',
    branch: 'main'
)));

$environment = $response->json()['data'];
```

### Run a Command

```php
use App\Client\Connector;
use App\Client\Requests\RunCommandRequestData;
use App\Client\Resources\Commands\RunCommandRequest;

$connector = new Connector('your-api-token');
$response = $connector->send(new RunCommandRequest(new RunCommandRequestData(
    environmentId: 'env-123',
    command: 'php artisan migrate'
)));

$command = $response->json()['data'];
```

### Create a Database Cluster

```php
use App\Client\Connector;
use App\Client\Requests\CreateDatabaseClusterRequestData;
use App\Client\Resources\DatabaseClusters\CreateDatabaseClusterRequest;

$connector = new Connector('your-api-token');
$response = $connector->send(new CreateDatabaseClusterRequest(new CreateDatabaseClusterRequestData(
    type: 'neon_serverless_postgres_18',
    name: 'my-database',
    region: 'us-east-1',
    clusterConfig: [
        'cu_min' => 0.25,
        'cu_max' => 2,
        'suspend_seconds' => 300,
        'retention_days' => 7,
    ]
));

$database = $response->json()['data'];
```

## Authentication

All requests require a Bearer token. You can generate API tokens from your Laravel Cloud organization settings.

The token is passed to the `Connector` constructor:

```php
$connector = new Connector('your-api-token');
```

## Response Format

All responses follow the JSON:API format. The main data is in the `data` key, and related resources may be in the `included` key.

```php
$response = $connector->send(new GetApplicationRequest('app-123'));
$data = $response->json();

// Main resource
$application = $data['data'];

// Included resources (if requested)
$organization = collect($data['included'] ?? [])
    ->firstWhere('type', 'organizations');
```

## Error Handling

Saloon will throw exceptions for HTTP errors. You can catch and handle them:

```php
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

try {
    $response = $connector->send(new GetApplicationRequest('app-123'));
} catch (RequestException $e) {
    // Handle request errors (4xx, 5xx)
    $statusCode = $e->getResponse()->status();
    $error = $e->getResponse()->json();
} catch (FatalRequestException $e) {
    // Handle fatal errors (network issues, etc.)
}
```

## Pagination

List endpoints return paginated results. Use the `links` and `meta` keys for pagination:

```php
$response = $connector->send(new ListApplicationsRequest());
$data = $response->json();

$applications = $data['data'];
$links = $data['links']; // first, last, prev, next
$meta = $data['meta']; // current_page, total, etc.
```

## Includes

Many endpoints support the `include` parameter to fetch related resources:

```php
$response = $connector->send(new GetApplicationRequest(
    applicationId: 'app-123',
    include: 'organization,environments,defaultEnvironment'
));
```

## Documentation

For full API documentation, see: https://cloud.laravel.com/docs/api/introduction
