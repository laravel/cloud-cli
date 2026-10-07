<?php

namespace App\Dto;

use App\Enums\DeploymentStatus;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class DeploymentLog extends Data
{
    public function __construct(
        #[WithCast(EnumCast::class)]
        public readonly DeploymentStatus $deploymentStatus,
        public readonly DeploymentLogPhase $build,
        public readonly DeploymentLogPhase $deploy,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];

        return new self(
            deploymentStatus: DeploymentStatus::from($response['meta']['deployment_status']),
            build: DeploymentLogPhase::fromApiResponse($data['build'] ?? []),
            deploy: DeploymentLogPhase::fromApiResponse($data['deploy'] ?? []),
        );
    }
}
