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

    protected function make(string $resolver): Resolver
    {
        return new $resolver($this->client, $this->localConfig, $this->isInteractive);
    }
}
