<?php

namespace App;

use App\Dto\Application;
use App\Dto\Database;
use App\Dto\DatabaseType;
use App\Dto\Deployment;
use App\Dto\Environment;
use App\Dto\EnvironmentInstance;
use App\Dto\Paginated;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CloudClient
{
    protected PendingRequest $client;

    public function __construct(
        protected string $apiKey,
    ) {
        $this->client = Http::withToken($this->apiKey)
            ->baseUrl('https://cloud.laravel.com/api')
            ->accept('application/json')
            ->throw();
    }

    /**
     * @return Paginated<Application>
     */
    public function listApplications(): Paginated
    {
        $response = $this->get('/applications', [
            'include' => implode(',', ['organization', 'environments', 'defaultEnvironment']),
        ]);

        return new Paginated(
            data: array_map(fn ($item) => Application::fromApiResponse($item), $response['data']),
            links: $response['links'],
        );
    }

    public function createApplication(string $repository, string $name, string $region): Application
    {
        $response = $this->post('/applications', [
            'repository' => $repository,
            'name' => $name,
            'region' => $region,
        ]);

        return Application::fromApiResponse($response['data']);
    }

    public function replaceEnvironmentVariables(string $environmentId, array $variables): array
    {
        $response = $this->client->post("/environments/{$environmentId}/variables", [
            'method' => 'append',
            'variables' => collect($variables)->map(fn ($value, $key) => [
                'key' => $key,
                'value' => $value,
            ])->toArray(),
        ]);

        return $response->json() ?? [];
    }

    public function getApplication(string $applicationId): Application
    {
        $response = $this->get("/applications/{$applicationId}", [
            'include' => implode(',', ['organization', 'environments', 'defaultEnvironment']),
        ]);

        return Application::fromApiResponse($response['data']);
    }

    /**
     * @return Paginated<Environment>
     */
    public function listEnvironments(string $applicationId): Paginated
    {
        $response = $this->get("/applications/{$applicationId}/environments");

        return new Paginated(
            data: array_map(fn ($item) => Environment::fromApiResponse($item), $response['data']),
            links: $response['links'],
        );
    }

    public function getEnvironment(string $environmentId): Environment
    {
        $response = $this->get("/environments/{$environmentId}", [
            'include' => implode(',', ['instances', 'currentDeployment']),
        ]);

        return Environment::fromApiResponse($response['data']);
    }

    public function updateEnvironment(string $environmentId, array $data): Environment
    {
        $response = $this->client->patch("/environments/{$environmentId}", $data);

        dump($response, $response->json());

        return Environment::fromApiResponse($response->json()['data']);
    }

    public function getInstance(string $instanceId): EnvironmentInstance
    {
        $response = $this->get("/instances/{$instanceId}");

        return EnvironmentInstance::fromApiResponse($response['data']);
    }

    public function updateInstance(string $instanceId, array $data): EnvironmentInstance
    {
        $response = $this->client->patch("/instances/{$instanceId}", $data);

        return EnvironmentInstance::fromApiResponse($response->json()['data']);
    }

    public function createEnvironment(string $applicationId, string $name, ?string $branch = null): Environment
    {
        $response = $this->post("/applications/{$applicationId}/environments", array_filter([
            'name' => $name,
            'branch' => $branch,
        ]));

        return Environment::fromApiResponse($response['data']);
    }

    public function initiateDeployment(string $environmentId): Deployment
    {
        $response = $this->post("/environments/{$environmentId}/deployments");

        return Deployment::fromApiResponse($response['data']);
    }

    public function getDeployment(string $deploymentId): Deployment
    {
        $response = $this->get("/deployments/{$deploymentId}");

        return Deployment::fromApiResponse($response['data']);
    }

    public function listDeployments(string $environmentId): Paginated
    {
        $response = $this->get("/environments/{$environmentId}/deployments");

        return new Paginated(
            data: array_map(fn ($item) => Deployment::fromApiResponse($item), $response['data']),
            links: $response['links'],
        );
    }

    protected function get(string $endpoint, array $query = []): array
    {
        $response = $this->client->get($endpoint, $query);

        return $response->json() ?? [];
    }

    protected function post(string $endpoint, array $data = []): array
    {
        $response = $this->client->post($endpoint, $data);

        return $response->json() ?? [];
    }

    /**
     * @return Paginated<Database>
     */
    public function listDatabases(): Paginated
    {
        $response = $this->get('/databases', [
            'include' => implode(',', ['schemas']),
        ]);

        dump($response);

        return new Paginated(
            data: array_map(fn ($item) => Database::fromApiResponse($item, $response), $response['data']),
            links: $response['links'],
        );
    }

    /**
     * @return DatabaseType[]
     */
    public function listDatabaseTypes(): array
    {
        $response = $this->get('/databases/types');

        return array_map(fn ($item) => DatabaseType::fromApiResponse($item), $response['data']);
    }

    public function getDatabase(string $databaseId): Database
    {
        $response = $this->get("/databases/{$databaseId}", [
            'include' => implode(',', ['schemas']),
        ]);

        return Database::fromApiResponse($response['data'], $response);
    }
}
