# Best Practices for AI Systems Using Laravel Cloud CLI

This document outlines best practices for AI systems consuming and using the Laravel Cloud CLI to ensure the most accurate and reliable experience.

## 1. Understanding Error Handling

### Exception-Based Error Handling
The `CloudClient` uses `->throw()` which means **all HTTP errors throw exceptions**. You must wrap API calls in try-catch blocks:

```php
try {
    $application = $client->getApplication($applicationId);
} catch (Saloon\Exceptions\Request\RequestException $e) {
    // Handle error
    $statusCode = $e->getResponse()->status();
    $errorBody = $e->getResponse()->json();
}
```

### Common HTTP Status Codes
- **422 Unprocessable Entity**: Validation errors - check `$e->response->json()['errors']`
- **404 Not Found**: Resource doesn't exist
- **401 Unauthorized**: Invalid or expired API token
- **403 Forbidden**: Insufficient permissions

### Validation Errors (422)
When you receive a 422 error, the response structure is:
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

## 2. Working with Pagination

### Understanding Paginated Responses
All list methods return a `Paginated<T>` object with:
- `data`: Array of DTO objects
- `links`: Pagination links (first, last, prev, next)

```php
$applications = $client->listApplications();

// Access data
foreach ($applications->data as $application) {
    // Process each application
}

// Check for more pages
if (isset($applications->links['next'])) {
    // There are more pages
}
```

### Important Notes
- The `Paginated` class doesn't have built-in methods for fetching next pages
- You'll need to manually parse `$links['next']` URL if you need pagination
- Consider the API rate limits when paginating

## 3. Using Includes for Related Resources

### Requesting Related Resources
Use the `include()` method **before** making a request to fetch related resources:

```php
// This will include related resources in the response
$client->include('instances', 'currentDeployment')
    ->getEnvironment($environmentId);

// Includes are reset after each request
// So you need to call include() again for the next request
```

### Common Include Options
- **Applications**: `organization`, `environments`, `defaultEnvironment`
- **Environments**: `instances`, `currentDeployment`, `domains`, `application`
- **Databases**: `schemas` (automatically included in list/get methods)

### Relationship IDs vs Full Objects
- DTOs always include relationship IDs (e.g., `environmentId`, `applicationId`)
- Full relationship objects are only included if you use `include()`
- Check the DTO properties to see what relationship IDs are available

## 4. Understanding DTO Structure

### All Responses Are Typed DTOs
Every API method returns a strongly-typed DTO:
- `Application`, `Environment`, `Deployment`, etc.
- All DTOs have `fromApiResponse()` static methods
- DTOs use readonly properties for immutability

### Accessing DTO Properties
```php
$environment = $client->getEnvironment($environmentId);

// Direct property access (readonly)
$name = $environment->name;
$status = $environment->statusEnum; // Enum type
$instances = $environment->instances; // Array of IDs
```

### Nullable Fields
Many fields are nullable. Always check:
```php
if ($deployment->finishedAt !== null) {
    // Deployment has finished
}
```

## 5. Working with Enums

### Status Enums
Several DTOs use enums for status:
- `DeploymentStatus` in `Deployment`
- `EnvironmentStatus` in `Environment`

```php
// Check status using enum
if ($deployment->status === DeploymentStatus::DEPLOYMENT_SUCCEEDED) {
    // Success
}

// Or use helper methods
if ($deployment->succeeded()) {
    // Success
}
```

## 6. Configuration Management

### Config Location
User configuration is stored in: `~/.config/cloud/config.json`

### Accessing Config
```php
$config = new ConfigRepository();
$apiKey = $config->get('api_key');
```

### Important Config Keys
- `api_key`: The Laravel Cloud API token
- Other keys may be stored for CLI state

## 7. Resource Relationships

### Understanding the Hierarchy
```
Organization
  └── Applications
      └── Environments
          ├── Instances
          │   └── Background Processes
          ├── Deployments
          ├── Domains
          └── Commands
```

### Getting Related Resources
```php
// Get application
$app = $client->getApplication($appId);

// Get environments for application
$environments = $client->listEnvironments($app->id);

// Get environment with instances
$env = $client->include('instances')->getEnvironment($envId);

// Get instances for environment
$instances = $client->listInstances($envId);
```

## 8. Deployment Workflow

### Typical Deployment Flow
```php
// 1. Get environment
$env = $client->getEnvironment($environmentId);

// 2. Initiate deployment
$deployment = $client->initiateDeployment($environmentId);

// 3. Poll for completion
while ($deployment->isInProgress()) {
    sleep(2);
    $deployment = $client->getDeployment($deployment->id);
}

// 4. Check result
if ($deployment->succeeded()) {
    // Success
} elseif ($deployment->failed()) {
    // Handle failure: $deployment->failureReason
}
```

### Deployment Status Methods
- `isPending()`: Deployment hasn't started
- `isBuilding()`: Currently building
- `isDeploying()`: Currently deploying
- `succeeded()`: Deployment succeeded
- `failed()`: Deployment failed
- `wasCancelled()`: Deployment was cancelled
- `isFinished()`: Any terminal state
- `isInProgress()`: Still running

## 9. Best Practices for Accuracy

### Always Use Specific Methods
```php
// ✅ Good: Use specific get method
$deployment = $client->getDeployment($deploymentId);

// ❌ Bad: Don't assume you can get from list
$deployments = $client->listDeployments($envId);
// This returns Paginated, not individual deployment
```

### Verify Resource Existence
```php
try {
    $app = $client->getApplication($appId);
} catch (RequestException $e) {
    if ($e->response->status() === 404) {
        // Application doesn't exist
    }
}
```

### Handle Async Operations
- Deployments are async - poll for completion
- Commands are async - check status
- Domain verification is async - check verification status

### Use Type Hints
All methods have return type hints:
```php
/** @return Paginated<Application> */
public function listApplications(): Paginated

/** @return DatabaseType[] */
public function listDatabaseTypes(): array
```

## 10. Common Pitfalls to Avoid

### ❌ Don't Assume Includes Persist
```php
// Includes are reset after each request
$client->include('instances')->getEnvironment($id1);
$env2 = $client->getEnvironment($id2); // No includes!
```

### ❌ Don't Ignore Pagination
```php
// If you need all results, handle pagination
$allApps = [];
$page = $client->listApplications();
$allApps = array_merge($allApps, $page->data);
// Check for next page...
```

### ❌ Don't Assume Relationships Are Loaded
```php
$env = $client->getEnvironment($id);
// $env->instances is an array of IDs, not objects
// Use include() or listInstances() to get full objects
```

### ❌ Don't Forget Error Handling
```php
// Always wrap in try-catch
try {
    $result = $client->createApplication(...);
} catch (RequestException $e) {
    // Handle error appropriately
}
```

## 11. Performance Considerations

### Minimize API Calls
- Use `include()` to fetch related resources in one call
- Cache frequently accessed resources
- Batch operations when possible

### Rate Limiting
- The API may have rate limits
- Implement exponential backoff for retries
- Don't poll too frequently (2-5 seconds is reasonable)

## 12. Testing and Validation

### Validate Input Before API Calls
```php
// Validate IDs are not empty
if (empty($applicationId)) {
    throw new InvalidArgumentException('Application ID is required');
}

// Validate enum values
if (!in_array($status, DeploymentStatus::cases())) {
    throw new InvalidArgumentException('Invalid status');
}
```

### Test Error Scenarios
- Test with invalid IDs (404)
- Test with invalid API keys (401)
- Test validation errors (422)
- Test network failures

## 13. Recommended Patterns

### Pattern: Safe Resource Access
```php
function safeGetEnvironment(CloudClient $client, string $envId): ?Environment {
    try {
        return $client->getEnvironment($envId);
    } catch (RequestException $e) {
        if ($e->response->status() === 404) {
            return null;
        }
        throw $e;
    }
}
```

### Pattern: Wait for Deployment
```php
function waitForDeployment(CloudClient $client, string $deploymentId, int $maxWait = 600): Deployment {
    $start = time();

    while (true) {
        $deployment = $client->getDeployment($deploymentId);

        if ($deployment->isFinished()) {
            return $deployment;
        }

        if (time() - $start > $maxWait) {
            throw new TimeoutException('Deployment timeout');
        }

        sleep(2);
    }
}
```

## 14. Summary Checklist

When using this CLI as an AI system:

- [ ] Always wrap API calls in try-catch blocks
- [ ] Check for null values on nullable properties
- [ ] Use `include()` when you need related resources
- [ ] Handle pagination if you need all results
- [ ] Use enum helper methods for status checks
- [ ] Poll async operations (deployments, commands)
- [ ] Verify resource existence before operations
- [ ] Respect rate limits and implement backoff
- [ ] Use type hints to understand return types
- [ ] Remember includes reset after each request
- [ ] Check relationship IDs vs full objects
- [ ] Handle 422 validation errors appropriately
