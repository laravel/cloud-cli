<?php

namespace App\Dto;

use App\Dto\Concerns\SpansMetricPeriod;
use Spatie\LaravelData\Data;

class WebsocketMetrics extends Data
{
    use SpansMetricPeriod;

    public function __construct(
        public readonly ?string $period,
        public readonly MetricSeries $connectionCount,
        public readonly MetricSeries $messageRate,
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
            connectionCount: MetricSeries::fromApiResponse($data['connection_count'] ?? []),
            messageRate: MetricSeries::fromApiResponse($data['message_rate'] ?? []),
            availablePeriods: self::availablePeriodsFrom($meta),
        );
    }

    public function allSeries(): array
    {
        return [$this->connectionCount, $this->messageRate];
    }
}
