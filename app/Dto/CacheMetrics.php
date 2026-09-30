<?php

namespace App\Dto;

use App\Dto\Concerns\SpansMetricPeriod;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class CacheMetrics extends Data
{
    use SpansMetricPeriod;

    public function __construct(
        public readonly ?string $period,
        public readonly MetricSeries $size,
        public readonly MetricSeries $bandwidthUsage,
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $hitsAndMisses = [],
        #[DataCollectionOf(MetricSeries::class)]
        public readonly array $throughput = [],
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
            size: MetricSeries::fromApiResponse($data['size'] ?? []),
            bandwidthUsage: MetricSeries::fromApiResponse($data['bandwidth_usage'] ?? []),
            hitsAndMisses: MetricSeries::listFromApiResponse($data['hits_and_misses'] ?? []),
            throughput: MetricSeries::listFromApiResponse($data['throughput'] ?? []),
            availablePeriods: self::availablePeriodsFrom($meta),
        );
    }

    public function allSeries(): array
    {
        return [
            $this->size,
            $this->bandwidthUsage,
            ...$this->hitsAndMisses,
            ...$this->throughput,
        ];
    }
}
