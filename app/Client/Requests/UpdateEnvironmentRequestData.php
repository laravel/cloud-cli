<?php

namespace App\Client\Requests;

use SensitiveParameter;

class UpdateEnvironmentRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly ?string $name = null,
        public readonly ?string $slug = null,
        public readonly ?string $color = null,
        public readonly ?string $branch = null,
        public readonly ?bool $usesPushToDeploy = null,
        public readonly ?bool $usesDeployHook = null,
        public readonly ?int $timeout = null,
        public readonly ?string $phpVersion = null,
        public readonly ?string $buildCommand = null,
        public readonly ?string $nodeVersion = null,
        public readonly ?string $deployCommand = null,
        public readonly ?bool $usesVanityDomain = null,
        public readonly ?string $databaseSchemaId = null,
        public readonly ?string $cacheId = null,
        public readonly ?string $websocketApplicationId = null,
        public readonly ?bool $usesOctane = null,
        public readonly ?int $sleepTimeout = null,
        public readonly ?int $shutdownTimeout = null,
        public readonly ?bool $usesPurgeEdgeCacheOnDeploy = null,
        #[SensitiveParameter]
        public readonly ?string $nightwatchToken = null,
        public readonly ?string $cacheStrategy = null,
        public readonly ?string $responseHeadersFrame = null,
        public readonly ?string $responseHeadersContentType = null,
        public readonly ?string $responseHeadersRobotsTag = null,
        /** @var array{max_age:int|null, include_subdomains: bool, preload: bool}|null */
        public readonly ?array $responseHeadersHsts = null,
        /** @var array<int, array{id: string, disk: string, is_default_disk: bool}>|null */
        public readonly ?array $filesystemKeys = null,
        public readonly ?string $firewallRateLimitLevel = null,
        public readonly ?bool $firewallUnderAttackMode = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'branch' => $this->branch,
            'uses_push_to_deploy' => $this->usesPushToDeploy,
            'uses_deploy_hook' => $this->usesDeployHook,
            'timeout' => $this->timeout,
            'php_version' => $this->phpVersion,
            'build_command' => $this->buildCommand,
            'node_version' => $this->nodeVersion,
            'deploy_command' => $this->deployCommand,
            'uses_vanity_domain' => $this->usesVanityDomain,
            'database_schema_id' => $this->databaseSchemaId,
            'cache_id' => $this->cacheId,
            'websocket_application_id' => $this->websocketApplicationId,
            'uses_octane' => $this->usesOctane,
            'sleep_timeout' => $this->sleepTimeout,
            'shutdown_timeout' => $this->shutdownTimeout,
            'uses_purge_edge_cache_on_deploy' => $this->usesPurgeEdgeCacheOnDeploy,
            'nightwatch_token' => $this->nightwatchToken,
            'cache_strategy' => $this->cacheStrategy,
            'response_headers_frame' => $this->responseHeadersFrame,
            'response_headers_content_type' => $this->responseHeadersContentType,
            'response_headers_robots_tag' => $this->responseHeadersRobotsTag,
            'response_headers_hsts' => $this->responseHeadersHsts,
            'filesystem_keys' => $this->filesystemKeys,
            'firewall_rate_limit_level' => $this->firewallRateLimitLevel,
            'firewall_under_attack_mode' => $this->firewallUnderAttackMode,
        ]);
    }
}
