<?php

namespace App;

use App\Dto\Application;
use App\Dto\BackgroundProcess;
use App\Dto\Command;
use App\Dto\Database;
use App\Dto\DatabaseType;
use App\Dto\Deployment;
use App\Dto\Domain;
use App\Dto\Environment;
use App\Dto\EnvironmentInstance;
use App\Dto\Paginated;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

class CloudClient
{
    /**
     * @var PendingRequest<false>
     */
    protected PendingRequest $client;

    protected array $includes = [];

    public function __construct(
        protected string $apiKey,
    ) {
        $this->client = Http::withToken($this->apiKey)
            ->baseUrl('https://cloud.laravel.com/api')
            ->acceptJson()
            ->asJson()
            ->withRequestMiddleware(function (RequestInterface $request) {
                if (empty($this->includes)) {
                    return $request;
                }

                $uri = $request->getUri();
                parse_str($uri->getQuery(), $queryParams);
                $queryParams['include'] = implode(',', $this->includes);

                return $request->withUri($uri->withQuery(http_build_query($queryParams)));
            })
            ->afterResponse(fn ($response) => $this->includes = [])
            ->throw();
    }

    /**
     * @return Paginated<Application>
     */
    public function listApplications(): Paginated
    {
        $this->include('organization', 'environments', 'defaultEnvironment');

        $response = $this->client->get('/applications');

        return new Paginated(
            data: array_map(fn ($item) => Application::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    public function createApplication(string $repository, string $name, string $region): Application
    {
        $response = $this->client->post('/applications', [
            'repository' => $repository,
            'name' => $name,
            'region' => $region,
        ]);

        return Application::fromApiResponse($response->json('data'));
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
        $this->include('organization', 'environments', 'defaultEnvironment');

        $response = $this->client->get("/applications/{$applicationId}");

        return Application::fromApiResponse($response->json('data'));
    }

    public function updateApplication(string $applicationId, array $data): Application
    {
        $response = $this->client->patch("/applications/{$applicationId}", $data);

        return Application::fromApiResponse($response->json('data'));
    }

    /**
     * @return Paginated<Environment>
     */
    public function listEnvironments(string $applicationId): Paginated
    {
        $response = $this->client->get("/applications/{$applicationId}/environments");

        return new Paginated(
            data: array_map(fn ($item) => Environment::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    public function getEnvironment(string $environmentId): Environment
    {
        $this->include('instances', 'currentDeployment');

        $response = $this->client->get("/environments/{$environmentId}");

        return Environment::fromApiResponse($response->json('data'));
    }

    public function updateEnvironment(string $environmentId, array $data): Environment
    {
        $response = $this->client->patch("/environments/{$environmentId}", $data);

        return Environment::fromApiResponse($response->json()['data']);
    }

    public function deleteEnvironment(string $environmentId): void
    {
        $this->client->delete("/environments/{$environmentId}");
    }

    public function getInstance(string $instanceId): EnvironmentInstance
    {
        $response = $this->client->get("/instances/{$instanceId}");

        return EnvironmentInstance::fromApiResponse($response->json('data'));
    }

    public function updateInstance(string $instanceId, array $data): EnvironmentInstance
    {
        $response = $this->client->patch("/instances/{$instanceId}", $data);

        return EnvironmentInstance::fromApiResponse($response->json('data'));
    }

    /**
     * @return Paginated<EnvironmentInstance>
     */
    public function listInstances(string $environmentId): Paginated
    {
        $response = $this->client->get("/environments/{$environmentId}/instances");

        return new Paginated(
            data: array_map(fn ($item) => EnvironmentInstance::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    public function createInstance(string $environmentId, array $data): EnvironmentInstance
    {
        $response = $this->client->post("/environments/{$environmentId}/instances", $data);

        return EnvironmentInstance::fromApiResponse($response->json('data'));
    }

    public function deleteInstance(string $instanceId): void
    {
        $this->client->delete("/instances/{$instanceId}");
    }

    public function createEnvironment(string $applicationId, string $name, ?string $branch = null): Environment
    {
        $response = $this->client->post(
            "/applications/{$applicationId}/environments",
            array_filter([
                'name' => $name,
                'branch' => $branch,
            ])
        );

        return Environment::fromApiResponse($response->json('data'));
    }

    public function initiateDeployment(string $environmentId): Deployment
    {
        $response = $this->client->post("/environments/{$environmentId}/deployments");

        return Deployment::fromApiResponse($response->json('data'));
    }

    public function getDeployment(string $deploymentId): Deployment
    {
        $response = $this->client->get("/deployments/{$deploymentId}");

        return Deployment::fromApiResponse($response->json('data'));
    }

    public function listDeployments(string $environmentId): Paginated
    {
        $response = $this->client->get("/environments/{$environmentId}/deployments");

        return new Paginated(
            data: array_map(fn ($item) => Deployment::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    /**
     * @return Paginated<Domain>
     */
    public function listDomains(string $environmentId): Paginated
    {
        $response = $this->client->get("/environments/{$environmentId}/domains");

        return new Paginated(
            data: array_map(fn ($item) => Domain::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    public function createDomain(string $environmentId, string $domain): Domain
    {
        $response = $this->client->post("/environments/{$environmentId}/domains", [
            'domain' => $domain,
        ]);

        return Domain::fromApiResponse($response->json('data'));
    }

    public function getDomain(string $domainId): Domain
    {
        $response = $this->client->get("/domains/{$domainId}");

        return Domain::fromApiResponse($response->json('data'));
    }

    public function updateDomain(string $domainId, array $data): Domain
    {
        $response = $this->client->patch("/domains/{$domainId}", $data);

        return Domain::fromApiResponse($response->json('data'));
    }

    public function deleteDomain(string $domainId): void
    {
        $this->client->delete("/domains/{$domainId}");
    }

    /**
     * @return Paginated<Command>
     */
    public function listCommands(string $environmentId): Paginated
    {
        $response = $this->client->get("/environments/{$environmentId}/commands");

        return new Paginated(
            data: array_map(fn ($item) => Command::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    public function runCommand(string $environmentId, string $command): Command
    {
        $response = $this->client->post("/environments/{$environmentId}/commands", [
            'command' => $command,
        ]);

        return Command::fromApiResponse($response->json('data'));
    }

    public function getCommand(string $commandId): Command
    {
        $response = $this->client->get("/commands/{$commandId}");

        return Command::fromApiResponse($response->json('data'));
    }

    /**
     * @return Paginated<BackgroundProcess>
     */
    public function listBackgroundProcesses(string $instanceId): Paginated
    {
        $response = $this->client->get("/instances/{$instanceId}/background-processes");

        return new Paginated(
            data: array_map(fn ($item) => BackgroundProcess::fromApiResponse($item), $response->json('data')),
            links: $response->json('links'),
        );
    }

    public function createBackgroundProcess(string $instanceId, array $data): BackgroundProcess
    {
        $response = $this->client->post("/instances/{$instanceId}/background-processes", $data);

        return BackgroundProcess::fromApiResponse($response->json('data'));
    }

    public function getBackgroundProcess(string $backgroundProcessId): BackgroundProcess
    {
        $response = $this->client->get("/background-processes/{$backgroundProcessId}");

        return BackgroundProcess::fromApiResponse($response->json('data'));
    }

    public function updateBackgroundProcess(string $backgroundProcessId, array $data): BackgroundProcess
    {
        $response = $this->client->patch("/background-processes/{$backgroundProcessId}", $data);

        return BackgroundProcess::fromApiResponse($response->json('data'));
    }

    public function deleteBackgroundProcess(string $backgroundProcessId): void
    {
        $this->client->delete("/background-processes/{$backgroundProcessId}");
    }

    public function getIpAddresses(): array
    {
        $response = $this->client->get('/ip');

        return $response->json() ?? [];
    }

    /**
     * @return Paginated<Database>
     */
    public function listDatabases(): Paginated
    {
        $this->include('schemas');

        $response = $this->client->get('/databases');

        return new Paginated(
            data: array_map(fn ($item) => Database::fromApiResponse($item, $response->json()), $response->json('data')),
            links: $response->json('links'),
        );
    }

    /**
     * @return DatabaseType[]
     */
    public function listDatabaseTypes(): array
    {
        $response = $this->client->get('/databases/types');

        return array_map(fn ($item) => DatabaseType::fromApiResponse($item), $response->json('data'));
    }

    public function getDatabase(string $databaseId): Database
    {
        $this->include('schemas');

        $response = $this->client->get("/databases/{$databaseId}");

        return Database::fromApiResponse($response->json('data'), $response->json());
    }

    public function include(string ...$includes): self
    {
        $this->includes = $includes;

        return $this;
    }
}
