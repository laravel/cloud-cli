<?php

namespace App\Commands;

use App\Dto\Usage as UsageDto;
use App\Dto\UsageBucket;
use App\Dto\UsageCache;
use App\Dto\UsageDatabase;
use App\Dto\UsageWebsocket;
use App\Support\Formatter;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;

class Usage extends BaseCommand
{
    protected ?string $jsonDataClass = UsageDto::class;

    protected $signature = 'usage
                            {--period=current : Billing period (current, previous, 1, 2, or 3)}
                            {--environment= : Filter usage by environment ID or name}
                            {--detailed : Show full breakdown with per-resource and per-application tables}';

    protected $description = 'View billing and usage for the current organization';

    public function handle()
    {
        $this->ensureClient();

        intro('Usage');

        $period = $this->resolvePeriod((string) $this->option('period'));

        $environmentId = $this->option('environment')
            ? $this->resolvers()->environment()->from($this->option('environment'))->id
            : null;

        $usage = spin(
            fn () => $this->client->usage()->get($period, $environmentId),
            'Fetching usage...',
        );

        $this->outputJsonIfWanted($usage);

        $periodLabel = $usage->periodDateRange() ?? match ($period) {
            0 => 'Current',
            1 => 'Previous',
            default => "{$period} periods ago",
        };

        $bandwidthLine = $usage->bandwidth === null
            ? '-'
            : sprintf(
                '%.1f%% of %s%s',
                $usage->bandwidth->usagePercentage,
                Formatter::bytes($usage->bandwidth->allowanceBytes),
                $usage->bandwidth->costCents > 0 ? ' ('.Formatter::centsToDollars($usage->bandwidth->costCents).')' : '',
            );

        dataList(array_filter([
            'Period' => $periodLabel,
            'Total Spend' => Formatter::centsToDollars($usage->currentSpendCents),
            'Credits Applied' => $usage->credits === null ? null : Formatter::centsToDollars($usage->credits->usedCents).' of '.Formatter::centsToDollars($usage->credits->totalCents),
            'Applications' => Formatter::centsToDollars($usage->applicationsTotalCostCents).' ('.$usage->applicationCount.' '.str('app')->plural($usage->applicationCount).')',
            'Resources' => Formatter::centsToDollars($usage->resourcesTotalCostCents),
            'Add-ons' => Formatter::centsToDollars($usage->addonsTotalCostCents),
            'Currency' => $usage->currency,
            'Bandwidth' => $bandwidthLine,
            'Last Updated' => $usage->lastUpdatedAt?->format('Y-m-d H:i:s') ?? '—',
        ], fn ($value) => $value !== null));

        if ($environmentId || $this->option('detailed')) {
            $this->renderEnvironmentUsage($usage);
        }

        if (! $this->option('detailed')) {
            return self::SUCCESS;
        }

        $this->renderApplicationUsage($usage);
        $this->renderDatabaseUsage($usage->databases);
        $this->renderCacheUsage($usage->caches);
        $this->renderBucketUsage($usage->buckets);
        $this->renderWebsocketUsage($usage->websockets);
        $this->renderAddonUsage($usage);

        return self::SUCCESS;
    }

    protected function renderEnvironmentUsage(UsageDto $usage): void
    {
        if ($usage->environmentUsageItems === []) {
            return;
        }

        $this->totalsHeader('Environment Usage', $usage->environmentUsageTotalCostCents);

        table(
            headers: ['Identifier', 'Type', 'Profile', 'CPU Hours', 'Cost'],
            rows: collect($usage->environmentUsageItems)->map(fn ($item, $i) => [
                $item->identifier,
                $item->type,
                $this->dataWithSubText(
                    $item->computeProfile,
                    $item->computeDescription,
                    $i === count($usage->environmentUsageItems) - 1,
                ),
                number_format($item->cpuHours, 2),
                $this->formatTotal($item->totalCents),
            ])->toArray(),
        );
    }

    protected function renderApplicationUsage(UsageDto $usage): void
    {
        if ($usage->applications === []) {
            return;
        }

        $this->totalsHeader('Applications', $usage->applicationsTotalCostCents);

        $applicationNames = spin(
            fn () => $this->client->applications()->list()->collect()->collect()->pluck('name', 'id'),
            'Fetching application details...',
        );

        table(
            headers: ['Name', 'Cost'],
            rows: collect($usage->applications)
                ->sortBy([
                    fn ($a, $b) => $a->totalCostCents <=> $b->totalCostCents,
                    fn ($a, $b) => $applicationNames->get($a->identifier) <=> $applicationNames->get($b->identifier),
                ])
                ->values()
                ->map(fn ($app, $i) => [
                    $this->dataWithSubText(
                        $applicationNames->get($app->identifier) ?? $app->identifier,
                        $app->identifier,
                        $i === count($usage->applications) - 1,
                    ),
                    $this->formatTotal($app->totalCostCents),
                ])
                ->toArray(),
        );
    }

    protected function renderAddonUsage(UsageDto $usage): void
    {
        if ($usage->addonItems === []) {
            return;
        }

        $this->totalsHeader('Add-ons', $usage->addonsTotalCostCents);

        table(
            headers: ['Name', 'Cost'],
            rows: collect($usage->addonItems)->map(fn ($item) => [
                $item->name,
                $this->formatTotal($item->totalCents),
            ])->toArray(),
        );
    }

    /**
     * @param  list<UsageDatabase>  $databases
     */
    protected function renderDatabaseUsage(array $databases): void
    {
        if ($databases === []) {
            return;
        }

        $this->totalsHeader('Databases', collect($databases)->sum('totalCents'));

        table(
            headers: [
                'Name',
                'Type',
                'Storage',
                'Compute',
                'Backups',
                'Total',
            ],
            rows: collect($databases)->map(fn ($item, $i) => [
                $this->dataWithSubText(
                    $item->name,
                    $item->identifier,
                    $i === count($databases) - 1,
                ),
                wordwrap($item->type, 20, "\n"),
                $this->totalWithSubText(
                    $item->storageCents,
                    Formatter::gigabyte($item->storageGb),
                ),
                $this->totalWithSubText(
                    $item->computeCents,
                    round($item->computeUnits, 1).' '.$item->computeUnitLabel,
                ),
                $this->totalWithSubText(
                    $item->backupsCents,
                    Formatter::gigabyte($item->backupsGb),
                ),
                $this->formatTotal($item->totalCents),
            ])->toArray(),
        );
    }

    /**
     * @param  list<UsageCache>  $caches
     */
    protected function renderCacheUsage(array $caches): void
    {
        if ($caches === []) {
            return;
        }

        $this->totalsHeader('Caches', collect($caches)->sum('totalCents'));

        table(
            headers: [
                'Name',
                'Type',
                'Storage',
                'Compute',
                'Total',
            ],
            rows: collect($caches)->map(fn ($item, $i) => [
                $this->dataWithSubText(
                    $item->name,
                    $item->identifier,
                    $i === count($caches) - 1,
                ),
                wordwrap($item->type, 20, "\n"),
                $this->dim($item->storage),
                $this->totalWithSubText(
                    $item->computeCents,
                    str('hour')->plural($item->computeHours, true),
                ),
                $this->formatTotal($item->totalCents),
            ])->toArray(),
        );
    }

    /**
     * @param  list<UsageBucket>  $buckets
     */
    protected function renderBucketUsage(array $buckets): void
    {
        if ($buckets === []) {
            return;
        }

        $this->totalsHeader('Buckets', collect($buckets)->sum('totalCents'));

        table(
            headers: [
                'Name',
                'Class A Requests',
                'Class B Requests',
                'Storage',
                'Total',
            ],
            rows: collect($buckets)->map(fn ($item, $i) => [
                $this->dataWithSubText(
                    $item->name,
                    $item->identifier,
                    $i === count($buckets) - 1,
                ),
                $this->totalWithSubText(
                    $item->classARequestsCents,
                    str('request')->plural($item->classARequestsCount, true),
                ),
                $this->totalWithSubText(
                    $item->classBRequestsCents,
                    str('request')->plural($item->classBRequestsCount, true),
                ),
                $this->totalWithSubText(
                    $item->storageCents,
                    Formatter::gigabyte($item->storageGb),
                ),
                $this->formatTotal($item->totalCents),
            ])->toArray(),
        );
    }

    /**
     * @param  list<UsageWebsocket>  $websockets
     */
    protected function renderWebsocketUsage(array $websockets): void
    {
        if ($websockets === []) {
            return;
        }

        $this->totalsHeader('Websockets', collect($websockets)->sum('totalCents'));

        table(
            headers: [
                'Name',
                'Max Concurrent Connections',
                'Usage Time',
                'Total',
            ],
            rows: collect($websockets)->map(fn ($item, $i) => [
                $this->dataWithSubText(
                    $item->name,
                    $item->identifier,
                    $i === count($websockets) - 1,
                ),
                $this->dim((string) $item->maxConnections),
                $this->totalWithSubText(
                    $item->usageTimeCents,
                    str('hour')->plural($item->usageTimeHours, true),
                ),
                $this->formatTotal($item->totalCents),
            ])->toArray(),
        );
    }

    protected function resolvePeriod(string $input): int
    {
        $valid = ['current', 'previous', '1', '2', '3'];

        if (! in_array($input, $valid)) {
            $this->failAndExit("Invalid --period value '{$input}'. Must be one of: ".implode(', ', $valid).'.');
        }

        return match ($input) {
            'current' => 0,
            'previous', '1' => 1,
            '2' => 2,
            '3' => 3,
            default => 0,
        };
    }

    protected function totalsHeader(string $title, int $totalCents): void
    {
        info($title.' '.$this->dim(Formatter::centsToDollars($totalCents)));
    }

    protected function totalWithSubText(?int $cents, string $subText): string
    {
        return $this->formatTotal($cents ?? 0)."\n".$this->dim($subText);
    }

    protected function dataWithSubText(string $text, string $subText, bool $isLast = false): string
    {
        return $text."\n".$this->dim(str($subText)->limit(20)).($isLast ? '' : "\n");
    }

    protected function formatTotal(?int $cents): string
    {
        $cents ??= 0;

        $formatted = Formatter::centsToDollars($cents);

        if ($cents === 0) {
            return $formatted;
        }

        return $this->cyan($this->bold($formatted));
    }
}
