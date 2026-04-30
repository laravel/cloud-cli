<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Usage extends Data
{
    public function __construct(
        public readonly string $currency,
        public readonly int $period,
        public readonly int $currentSpendCents,
        public readonly ?UsageBandwidth $bandwidth,
        public readonly ?UsageCredits $credits,
        public readonly ?UsageAlert $alert,
        public readonly int $resourcesTotalCostCents,
        public readonly int $addonsTotalCostCents,
        public readonly int $applicationCount,
        public readonly int $applicationsTotalCostCents,
        public readonly int $environmentUsageTotalCostCents,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $lastUpdatedAt,
        #[DataCollectionOf(UsagePeriod::class)]
        public readonly array $availablePeriods = [],
        #[DataCollectionOf(UsageDatabase::class)]
        public readonly array $databases = [],
        #[DataCollectionOf(UsageCache::class)]
        public readonly array $caches = [],
        #[DataCollectionOf(UsageBucket::class)]
        public readonly array $buckets = [],
        #[DataCollectionOf(UsageWebsocket::class)]
        public readonly array $websockets = [],
        #[DataCollectionOf(UsageApplication::class)]
        public readonly array $applications = [],
        #[DataCollectionOf(UsageAddon::class)]
        public readonly array $addonItems = [],
        #[DataCollectionOf(UsageEnvironmentItem::class)]
        public readonly array $environmentUsageItems = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $meta = $response['meta'] ?? [];
        $summary = $data['summary'] ?? [];
        $resources = $data['resources'] ?? [];
        $addons = $data['addons'] ?? [];
        $appTotals = $data['application_totals'] ?? [];
        $envUsage = $data['environment_usage'] ?? [];

        return new self(
            currency: $meta['currency'] ?? 'USD',
            period: $meta['period'] ?? 0,
            currentSpendCents: $summary['current_spend_cents'] ?? 0,
            bandwidth: isset($summary['bandwidth']) ? UsageBandwidth::fromApiResponse($summary['bandwidth']) : null,
            credits: isset($summary['credits']) ? UsageCredits::fromApiResponse($summary['credits']) : null,
            alert: isset($summary['alert']) ? UsageAlert::fromApiResponse($summary['alert']) : null,
            resourcesTotalCostCents: $resources['total_cost_cents'] ?? 0,
            addonsTotalCostCents: $addons['total_cost_cents'] ?? 0,
            applicationCount: $appTotals['application_count'] ?? 0,
            applicationsTotalCostCents: $appTotals['total_cost_cents'] ?? 0,
            environmentUsageTotalCostCents: $envUsage['total_cost_cents'] ?? 0,
            lastUpdatedAt: isset($meta['last_updated_at']) ? CarbonImmutable::parse($meta['last_updated_at']) : null,
            availablePeriods: array_map(UsagePeriod::fromApiResponse(...), $meta['available_periods'] ?? []),
            databases: array_map(UsageDatabase::fromApiResponse(...), $resources['databases'] ?? []),
            caches: array_map(UsageCache::fromApiResponse(...), $resources['caches'] ?? []),
            buckets: array_map(UsageBucket::fromApiResponse(...), $resources['buckets'] ?? []),
            websockets: array_map(UsageWebsocket::fromApiResponse(...), $resources['websockets'] ?? []),
            applications: array_map(UsageApplication::fromApiResponse(...), $appTotals['applications'] ?? []),
            addonItems: array_map(UsageAddon::fromApiResponse(...), $addons['items'] ?? []),
            environmentUsageItems: array_map(UsageEnvironmentItem::fromApiResponse(...), $envUsage['items'] ?? []),
        );
    }

    public function periodDateRange(): ?string
    {
        $p = $this->availablePeriods[$this->period] ?? null;

        if (! $p instanceof UsagePeriod || (! $p->from && ! $p->to)) {
            return null;
        }

        $from = $p->from ? CarbonImmutable::parse($p->from)->format('M j, Y') : null;
        $to = $p->to ? CarbonImmutable::parse($p->to)->format('M j, Y') : null;

        return implode(' – ', array_filter([$from, $to]));
    }
}
