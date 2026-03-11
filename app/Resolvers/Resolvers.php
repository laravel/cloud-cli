<?php

namespace App\Resolvers;

use App\Client\Connector;
use App\LocalConfig;

class Resolvers
{
    public function __construct(
        protected Connector $client,
        protected LocalConfig $localConfig,
        protected bool $isInteractive,
    ) {
        //
    }

    public function application(): ApplicationResolver
    {
        return $this->make(ApplicationResolver::class);
    }

    public function environment(): EnvironmentResolver
    {
        return $this->make(EnvironmentResolver::class);
    }

    public function instance(): InstanceResolver
    {
        return $this->make(InstanceResolver::class);
    }

    public function backgroundProcess(): BackgroundProcessResolver
    {
        return $this->make(BackgroundProcessResolver::class);
    }

    public function command(): CommandResolver
    {
        return $this->make(CommandResolver::class);
    }

    public function databaseCluster(): DatabaseClusterResolver
    {
        return $this->make(DatabaseClusterResolver::class);
    }

    public function database(): DatabaseResolver
    {
        return $this->make(DatabaseResolver::class);
    }

    public function databaseSnapshot(): DatabaseSnapshotResolver
    {
        return $this->make(DatabaseSnapshotResolver::class);
    }

    public function deployment(): DeploymentResolver
    {
        return $this->make(DeploymentResolver::class);
    }

    public function domain(): DomainResolver
    {
        return $this->make(DomainResolver::class);
    }

    public function cache(): CacheResolver
    {
        return $this->make(CacheResolver::class);
    }

    public function objectStorageBucket(): ObjectStorageBucketResolver
    {
        return $this->make(ObjectStorageBucketResolver::class);
    }

    public function bucketKey(): BucketKeyResolver
    {
        return $this->make(BucketKeyResolver::class);
    }

    public function websocketCluster(): WebSocketClusterResolver
    {
        return $this->make(WebSocketClusterResolver::class);
    }

    public function websocketApplication(): WebSocketApplicationResolver
    {
        return $this->make(WebSocketApplicationResolver::class);
    }

    /**
     * @template T of Resolver
     *
     * @param  class-string<T>  $resolver
     * @return T
     */
    protected function make(string $resolver): Resolver
    {
        return new $resolver($this->client, $this->localConfig, $this->isInteractive);
    }
}
