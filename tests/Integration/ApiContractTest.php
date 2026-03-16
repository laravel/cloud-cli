<?php

use App\Client\Connector;
use App\Dto\Application;
use App\Dto\Cache;
use App\Dto\DatabaseCluster;
use App\Dto\ObjectStorageBucket;
use App\Dto\Organization;
use App\Dto\Region;

/*
|--------------------------------------------------------------------------
| Integration Tests — API Contract Validation
|--------------------------------------------------------------------------
|
| These tests hit the real Laravel Cloud API to catch contract drift between
| the CLI's DTOs and the actual API responses. They are READ-ONLY and will
| never create, update, or delete any resource.
|
| Run with: LARAVEL_CLOUD_API_TOKEN=xxx ./vendor/bin/pest --group=integration
|
*/

beforeEach(function () {
    $token = getenv('LARAVEL_CLOUD_API_TOKEN');

    if (! $token) {
        $this->markTestSkipped('LARAVEL_CLOUD_API_TOKEN not set — skipping integration test.');
    }

    $this->connector = new Connector($token);
});

it('can list applications', function () {
    $paginator = $this->connector->applications()->withDefaultIncludes()->list();
    $applications = $paginator->collect()->collapse();

    expect($applications)->toBeInstanceOf(\Illuminate\Support\LazyCollection::class);

    $applications->each(function ($app) {
        expect($app)->toBeInstanceOf(Application::class);
        expect($app->id)->toBeString()->not->toBeEmpty();
        expect($app->name)->toBeString()->not->toBeEmpty();
        expect($app->slug)->toBeString()->not->toBeEmpty();
        expect($app->region)->toBeString()->not->toBeEmpty();
    });
})->group('integration');

it('can get an application by ID', function () {
    $paginator = $this->connector->applications()->withDefaultIncludes()->list();
    $applications = $paginator->collect()->collapse();
    $first = $applications->first();

    if (! $first) {
        $this->markTestSkipped('No applications found — cannot test get by ID.');
    }

    $application = $this->connector->applications()->withDefaultIncludes()->get($first->id);

    expect($application)->toBeInstanceOf(Application::class);
    expect($application->id)->toBe($first->id);
    expect($application->name)->toBeString()->not->toBeEmpty();
    expect($application->slug)->toBeString()->not->toBeEmpty();
    expect($application->region)->toBeString()->not->toBeEmpty();
})->group('integration');

it('can list environments for an application', function () {
    $paginator = $this->connector->applications()->withDefaultIncludes()->list();
    $applications = $paginator->collect()->collapse();
    $first = $applications->first();

    if (! $first) {
        $this->markTestSkipped('No applications found — cannot test environments listing.');
    }

    $envPaginator = $this->connector->environments()->list($first->id);
    $environments = $envPaginator->collect()->collapse();

    expect($environments)->toBeInstanceOf(\Illuminate\Support\LazyCollection::class);

    $environments->each(function ($env) {
        expect($env)->toBeInstanceOf(\App\Dto\Environment::class);
        expect($env->id)->toBeString()->not->toBeEmpty();
        expect($env->name)->toBeString()->not->toBeEmpty();
        expect($env->slug)->toBeString()->not->toBeEmpty();
    });
})->group('integration');

it('can list database clusters', function () {
    $paginator = $this->connector->databaseClusters()->list();
    $clusters = $paginator->collect()->collapse();

    expect($clusters)->toBeInstanceOf(\Illuminate\Support\LazyCollection::class);

    $clusters->each(function ($cluster) {
        expect($cluster)->toBeInstanceOf(DatabaseCluster::class);
        expect($cluster->id)->toBeString()->not->toBeEmpty();
        expect($cluster->name)->toBeString()->not->toBeEmpty();
        expect($cluster->type)->toBeString()->not->toBeEmpty();
        expect($cluster->status)->toBeString()->not->toBeEmpty();
        expect($cluster->region)->toBeString()->not->toBeEmpty();
    });
})->group('integration');

it('can list caches', function () {
    $paginator = $this->connector->caches()->list();
    $caches = $paginator->collect()->collapse();

    expect($caches)->toBeInstanceOf(\Illuminate\Support\LazyCollection::class);

    $caches->each(function ($cache) {
        expect($cache)->toBeInstanceOf(Cache::class);
        expect($cache->id)->toBeString()->not->toBeEmpty();
        expect($cache->name)->toBeString()->not->toBeEmpty();
        expect($cache->type)->toBeString()->not->toBeEmpty();
        expect($cache->status)->toBeString()->not->toBeEmpty();
        expect($cache->region)->toBeString()->not->toBeEmpty();
    });
})->group('integration');

it('can list buckets', function () {
    $paginator = $this->connector->objectStorageBuckets()->list();
    $buckets = $paginator->collect()->collapse();

    expect($buckets)->toBeInstanceOf(\Illuminate\Support\LazyCollection::class);

    $buckets->each(function ($bucket) {
        expect($bucket)->toBeInstanceOf(ObjectStorageBucket::class);
        expect($bucket->id)->toBeString()->not->toBeEmpty();
        expect($bucket->name)->toBeString()->not->toBeEmpty();
    });
})->group('integration');

it('can list IP addresses', function () {
    $ipAddresses = $this->connector->meta()->ipAddresses();

    expect($ipAddresses)->toBeArray();
})->group('integration');

it('can get organization metadata', function () {
    $organization = $this->connector->meta()->organization();

    expect($organization)->toBeInstanceOf(Organization::class);
    expect($organization->id)->toBeString()->not->toBeEmpty();
    expect($organization->name)->toBeString()->not->toBeEmpty();
    expect($organization->slug)->toBeString()->not->toBeEmpty();
})->group('integration');

it('can list regions', function () {
    $regions = $this->connector->meta()->regions();

    expect($regions)->toBeArray()->not->toBeEmpty();

    foreach ($regions as $region) {
        expect($region)->toBeInstanceOf(Region::class);
        expect($region->value)->toBeString()->not->toBeEmpty();
        expect($region->label)->toBeString()->not->toBeEmpty();
        expect($region->flag)->toBeString()->not->toBeEmpty();
    }
})->group('integration');
