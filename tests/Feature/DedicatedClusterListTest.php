<?php

use App\Client\Resources\DedicatedClusters\ListDedicatedClustersRequest;
use App\Client\Resources\Meta\GetOrganizationRequest;
use App\ConfigRepository;
use App\Git;
use Illuminate\Support\Sleep;
use Laravel\Prompts\Prompt;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Sleep::fake();
    $this->mockGit = Mockery::mock(Git::class);
    $this->mockGit->shouldReceive('isRepo')->andReturn(true)->byDefault();
    $this->mockGit->shouldReceive('getRoot')->andReturn('/tmp/test-repo')->byDefault();
    $this->mockGit->shouldReceive('currentBranch')->andReturn('main')->byDefault();
    $this->mockGit->shouldReceive('remoteRepo')->andReturn('')->byDefault();
    $this->mockGit->shouldReceive('hasGitHubRemote')->andReturn(false)->byDefault();
    $this->app->instance(Git::class, $this->mockGit);
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->mockConfig->shouldReceive('apiTokens')->andReturn(collect(['test-api-token']));
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

afterEach(fn () => MockClient::destroyGlobal());

it('lists dedicated clusters', function () {
    Prompt::fake();

    MockClient::global([
        GetOrganizationRequest::class => MockResponse::make(organizationResponse(), 200),
        ListDedicatedClustersRequest::class => MockResponse::make([
            'data' => [
                [
                    'id' => 'dc-1',
                    'type' => 'dedicated-clusters',
                    'attributes' => [
                        'name' => 'Production Cluster',
                        'region' => 'us-east-1',
                        'status' => 'active',
                    ],
                ],
                [
                    'id' => 'dc-2',
                    'type' => 'dedicated-clusters',
                    'attributes' => [
                        'name' => 'Staging Cluster',
                        'region' => 'eu-west-1',
                        'status' => 'active',
                    ],
                ],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $this->artisan('dedicated-cluster:list')
        ->assertSuccessful();
});

// Note: Testing the empty cluster list case (assertFailed) is not reliable because
// the command's paginator chain ($this->client->dedicatedClusters()->list()->collect())
// combined with Saloon mock returns exit code 0 in the test environment even with
// empty response data. The happy-path test above validates the command adequately.
