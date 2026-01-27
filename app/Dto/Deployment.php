<?php

namespace App\Dto;

use App\Enums\DeploymentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class Deployment extends Data
{
    public function __construct(
        public readonly string $id,
        #[WithCast(EnumCast::class)]
        public readonly DeploymentStatus $status,
        public readonly ?string $commitHash = null,
        public readonly ?string $commitMessage = null,
        public readonly ?string $commitAuthor = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $startedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $finishedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $updatedAt = null,
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

    public function totalTime(): CarbonInterval
    {
        if (! $this->startedAt || ! $this->finishedAt) {
            return CarbonInterval::seconds(0);
        }

        return $this->finishedAt->diff($this->startedAt);
    }

    public function timeElapsed(): CarbonInterval
    {
        if (! $this->startedAt) {
            return CarbonInterval::seconds(0);
        }

        return $this->startedAt->diff(CarbonImmutable::now());
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

    public function succeeded(): bool
    {
        return $this->status === DeploymentStatus::DEPLOYMENT_SUCCEEDED;
    }

    public function failed(): bool
    {
        return $this->status === DeploymentStatus::DEPLOYMENT_FAILED || $this->status === DeploymentStatus::BUILD_FAILED;
    }

    public function wasCancelled(): bool
    {
        return $this->status === DeploymentStatus::CANCELLED;
    }

    public function isFinished(): bool
    {
        return $this->succeeded() || $this->failed() || $this->wasCancelled();
    }

    public function isInProgress(): bool
    {
        return ! $this->isFinished();
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];
        $commit = $attributes['commit'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'status' => $attributes['status'] ?? 'pending',
            'commitHash' => $commit['hash'] ?? $attributes['commit_hash'] ?? null,
            'commitMessage' => $commit['message'] ?? $attributes['commit_message'] ?? null,
            'commitAuthor' => $commit['author'] ?? $attributes['commit_author'] ?? null,
            'startedAt' => $attributes['started_at'] ?? null,
            'finishedAt' => $attributes['finished_at'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'updatedAt' => $attributes['updated_at'] ?? null,
            'failureReason' => $attributes['failure_reason'] ?? null,
            'branchName' => $attributes['branch_name'] ?? '',
            'phpMajorVersion' => $attributes['php_major_version'] ?? '',
            'buildCommand' => $attributes['build_command'] ?? null,
            'nodeVersion' => $attributes['node_version'] ?? null,
            'usesOctane' => $attributes['uses_octane'] ?? false,
            'usesHibernation' => $attributes['uses_hibernation'] ?? false,
        ];

        if (isset($relationships['environment']['data']['id'])) {
            $transformed['environmentId'] = $relationships['environment']['data']['id'];
        }

        if (isset($relationships['initiator']['data']['id'])) {
            $transformed['initiatorId'] = $relationships['initiator']['data']['id'];
        }

        return self::from($transformed);
    }
}
