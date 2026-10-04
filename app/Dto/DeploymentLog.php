<?php

namespace App\Dto;

use Spatie\LaravelData\Data;

class DeploymentLog extends Data
{
    public function __construct(
        public readonly string $deploymentId,
        public readonly ?string $deploymentStatus,
        public readonly DeploymentLogPhase $build,
        public readonly DeploymentLogPhase $deploy,
    ) {
        //
    }

    public static function createFromResponse(string $deploymentId, array $response): self
    {
        $data = $response['data'] ?? [];

        return new self(
            deploymentId: $deploymentId,
            deploymentStatus: $response['meta']['deployment_status'] ?? null,
            build: DeploymentLogPhase::createFromResponse($data['build'] ?? []),
            deploy: DeploymentLogPhase::createFromResponse($data['deploy'] ?? []),
        );
    }
}
