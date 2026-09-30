<?php

namespace App\Dto;

use App\Dto\Concerns\SpansMetricPeriod;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class EnvironmentMetrics extends Data
{
    use SpansMetricPeriod;

    public function __construct(
        public readonly ?string $period,
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $cpuUsage = [],
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $memoryUsage = [],
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $httpResponseCount = [],
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $replicaCount = [],
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $webWorkersCount = [],
        /** @var list<string> */
        public readonly array $availablePeriods = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];

        return new self(
            period: $meta['period'] ?? null,
            cpuUsage: MetricSeries::listFromApiResponse($data['cpu_usage'] ?? []),
            memoryUsage: MetricSeries::listFromApiResponse($data['memory_usage'] ?? []),
            httpResponseCount: MetricSeries::listFromApiResponse($data['http_response_count'] ?? []),
            replicaCount: MetricSeries::listFromApiResponse($data['replica_count'] ?? []),
            webWorkersCount: MetricSeries::listFromApiResponse($data['web_workers_count'] ?? []),
            availablePeriods: self::availablePeriodsFrom($meta),
        );
    }

    public function allSeries(): array
    {
        return [
            ...$this->cpuUsage,
            ...$this->memoryUsage,
            ...$this->httpResponseCount,
            ...$this->replicaCount,
            ...$this->webWorkersCount,
        ];
    }
}
