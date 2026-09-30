<?php

namespace App\Dto;

use App\Dto\Concerns\SpansMetricPeriod;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class DatabaseClusterMetrics extends Data
{
    use SpansMetricPeriod;

    public function __construct(
        public readonly ?string $period,
        public readonly MetricSeries $cpuUsage,
        public readonly MetricSeries $computeHours,
        public readonly MetricSeries $memoryUsage,
        public readonly MetricSeries $writes,
        public readonly MetricSeries $storageUsage,
        /** @var list<string> */
        public readonly array $availablePeriods = [],
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $replicaLag = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];

        return new self(
            period: $meta['period'] ?? null,
            cpuUsage: MetricSeries::fromApiResponse($data['cpu_usage'] ?? []),
            computeHours: MetricSeries::fromApiResponse($data['compute_hours'] ?? []),
            memoryUsage: MetricSeries::fromApiResponse($data['memory_usage'] ?? []),
            writes: MetricSeries::fromApiResponse($data['writes'] ?? []),
            storageUsage: MetricSeries::fromApiResponse($data['storage_usage'] ?? []),
            availablePeriods: self::availablePeriodsFrom($meta),
            replicaLag: MetricSeries::listFromApiResponse($data['replica_lag'] ?? []),
        );
    }

    public function allSeries(): array
    {
        return [
            $this->cpuUsage,
            $this->computeHours,
            $this->memoryUsage,
            $this->writes,
            $this->storageUsage,
            ...$this->replicaLag,
        ];
    }
}
