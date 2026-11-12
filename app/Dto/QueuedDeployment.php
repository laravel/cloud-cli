<?php

namespace App\Dto;

use App\Client\JsonApiTransformer;
use App\Enums\QueuedDeploymentStatus;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * A deployment Cloud holds back until the environment's resources finish provisioning.
 */
class QueuedDeployment extends Data
{
    public function __construct(
        public readonly string $id,
        #[WithCast(EnumCast::class)]
        public readonly QueuedDeploymentStatus $status,
        public readonly ?string $branchName = null,
        public readonly ?string $commitHash = null,
        public readonly ?string $commitMessage = null,
        public readonly ?string $commitAuthor = null,
        public readonly ?string $failureReason = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?Deployment $deployment = null,
    ) {
        //
    }

    public function isPending(): bool
    {
        return $this->status === QueuedDeploymentStatus::PENDING;
    }

    public function wasPromoted(): bool
    {
        return $this->status === QueuedDeploymentStatus::PROMOTED;
    }

    public function notStartedReason(): string
    {
        return $this->failureReason ?? match ($this->status) {
            QueuedDeploymentStatus::PROMOTED => 'Cloud started the deployment but did not return it',
            default => 'Queued deployment '.strtolower($this->status->label()),
        };
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];
        $included = $response['included'] ?? [];

        $deployment = JsonApiTransformer::resolveIncluded($included, $relationships['deployment'] ?? null, 'deployments');

        return self::from([
            'id' => $data['id'],
            'status' => $attributes['status'] ?? 'pending',
            'branchName' => $attributes['branch_name'] ?? null,
            'commitHash' => $attributes['commit_hash'] ?? null,
            'commitMessage' => $attributes['commit_message'] ?? null,
            'commitAuthor' => $attributes['commit_author'] ?? null,
            'failureReason' => $attributes['failure_reason'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'deployment' => $deployment ? Deployment::createFromResponse(['data' => $deployment, 'included' => $included]) : null,
        ]);
    }
}
