<?php

namespace App\Dto;

use App\Enums\DeploymentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;

class Deployment
{
    public function __construct(
        public readonly string $id,
        public readonly DeploymentStatus $status,
        public readonly ?string $commitHash = null,
        public readonly ?string $commitMessage = null,
        public readonly ?string $commitAuthor = null,
        public readonly ?CarbonImmutable $startedAt = null,
        public readonly ?CarbonImmutable $finishedAt = null,
        public readonly ?string $failureReason = null,
        public readonly string $branchName = '',
        public readonly string $phpMajorVersion = '',
        public readonly ?string $buildCommand = null,
        public readonly ?string $nodeVersion = null,
        public readonly bool $usesOctane = false,
        public readonly bool $usesHibernation = false,
        public readonly ?string $environmentId = null,
        public readonly ?string $initiatorId = null,
    ) {
        //
    }

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];
        $commit = $attributes['commit'] ?? [];

        return new self(
            id: $data['id'],
            status: DeploymentStatus::from($attributes['status'] ?? 'pending'),
            commitHash: $commit['hash'] ?? $attributes['commit_hash'] ?? null,
            commitMessage: $commit['message'] ?? $attributes['commit_message'] ?? null,
            commitAuthor: $commit['author'] ?? $attributes['commit_author'] ?? null,
            startedAt: isset($attributes['started_at']) ? CarbonImmutable::parse($attributes['started_at']) : null,
            finishedAt: isset($attributes['finished_at']) ? CarbonImmutable::parse($attributes['finished_at']) : null,
            failureReason: $attributes['failure_reason'] ?? null,
            branchName: $attributes['branch_name'] ?? '',
            phpMajorVersion: $attributes['php_major_version'] ?? '',
            buildCommand: $attributes['build_command'] ?? null,
            nodeVersion: $attributes['node_version'] ?? null,
            usesOctane: $attributes['uses_octane'] ?? false,
            usesHibernation: $attributes['uses_hibernation'] ?? false,
            environmentId: $relationships['environment']['data']['id'] ?? null,
            initiatorId: $relationships['initiator']['data']['id'] ?? null,
        );
    }

    public function totalTime(): CarbonInterval
    {
        if (! $this->startedAt || ! $this->finishedAt) {
            return CarbonInterval::seconds(0);
        }

        return $this->finishedAt->diff($this->startedAt);
    }

    public function isPending(): bool
    {
        return $this->status === DeploymentStatus::PENDING;
    }

    public function isBuilding(): bool
    {
        return $this->status === DeploymentStatus::BUILD_RUNNING;
    }

    public function isDeploying(): bool
    {
        return $this->status === DeploymentStatus::DEPLOYMENT_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === DeploymentStatus::DEPLOYMENT_SUCCEEDED;
    }

    public function isFailed(): bool
    {
        return $this->status === DeploymentStatus::DEPLOYMENT_FAILED || $this->status === DeploymentStatus::BUILD_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === DeploymentStatus::CANCELLED;
    }

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed() || $this->isCancelled();
    }

    public function isInProgress(): bool
    {
        return ! $this->isFinished();
    }
}
