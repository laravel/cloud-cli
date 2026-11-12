<?php

namespace App\Dto;

use App\Enums\CommandStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class Command extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $command,
        #[WithCast(EnumCast::class)]
        public readonly CommandStatus $status,
        public readonly ?string $output = null,
        public readonly bool $outputTruncated = false,
        public readonly ?string $outputUrl = null,
        public readonly ?int $exitCode = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $startedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $finishedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $failureReason = null,
        public readonly ?Environment $environment = null,
        public readonly ?Deployment $deployment = null,
        public readonly ?User $initiator = null,
    ) {
        //
    }

    public function isFinished(): bool
    {
        return $this->status === CommandStatus::SUCCESS || $this->status === CommandStatus::FAILURE;
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

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'command' => $attributes['command'] ?? '',
            'status' => $attributes['status'] ?? '',
            'output' => $attributes['output'] ?? null,
            'outputTruncated' => $attributes['output_truncated'] ?? false,
            'outputUrl' => $attributes['output_url'] ?? null,
            'exitCode' => $attributes['exit_code'] ?? null,
            'startedAt' => $attributes['started_at'] ?? null,
            'finishedAt' => $attributes['finished_at'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'failureReason' => $attributes['failure_reason'] ?? null,
        ];

        $included = $response['included'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $environment = self::resolveIncluded($included, $relationships['environment'] ?? null, 'environments');
        $deployment = self::resolveIncluded($included, $relationships['deployment'] ?? null, 'deployments');
        $initiator = self::resolveIncluded($included, $relationships['initiator'] ?? null, 'users');

        $transformed['environment'] = $environment ? Environment::createFromResponse(['data' => $environment, 'included' => $included]) : null;
        $transformed['deployment'] = $deployment ? Deployment::createFromResponse(['data' => $deployment, 'included' => $included]) : null;
        $transformed['initiator'] = $initiator ? User::createFromResponse(['data' => $initiator, 'included' => $included]) : null;

        return self::from($transformed);
    }

    protected static function resolveIncluded(array $included, ?array $relationship, string $type): ?array
    {
        if (! isset($relationship['data']['id'])) {
            return null;
        }

        $id = $relationship['data']['id'];

        return collect($included)->first(fn ($item) => $item['type'] === $type && $item['id'] === $id);
    }

    protected function overrideDescriptionItem(string $key, mixed $value): ?array
    {
        if ($key === 'status') {
            return [
                'Status' => $value->label(),
            ];
        }

        if ($key === 'environment' && $value) {
            return [
                'Environment' => $value['name'] ?? null,
            ];
        }

        if ($key === 'deployment' && $value) {
            return [
                'Deployment' => $value['id'] ?? null,
            ];
        }

        if ($key === 'initiator' && $value) {
            return [
                'Initiator' => $value['name'],
            ];
        }

        return null;
    }
}
