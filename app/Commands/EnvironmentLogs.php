<?php

namespace App\Commands;

use App\Dto\EnvironmentLog;
use App\Prompts\EnvironmentLogsPrompt;
use Carbon\CarbonImmutable;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentLogs extends BaseCommand
{
    // The logs endpoint shares a 30 requests/minute per-organization limit with resource creation.
    protected const MAX_LIMIT = 1000;

    protected $signature = 'environment:logs
                            {application? : The application ID or name}
                            {environment? : The name or ID of the environment}
                            {--from= : Start time for filtering logs}
                            {--days= : Number of days to fetch logs}
                            {--hours= : Number of hours to fetch logs}
                            {--minutes= : Number of minutes to fetch logs}
                            {--to= : End time for filtering logs}
                            {--tail= : Number of lines to show from the end}
                            {--limit= : Fetch up to this many of the newest lines, paging through the API (100 lines per page, max 1000)}
                            {--live : Live log output}';

    protected $description = 'View environment logs';

    protected $aliases = ['env:logs'];

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Logs');

        $limit = $this->resolveLimit();

        $app = $this->resolvers()->application()->from($this->argument('application'));
        $environment = $this->resolvers()->environment()->withApplication($app)->from($this->argument('environment'));

        $from = $this->resolveFrom();
        $to = $this->resolveTo();

        if ($this->option('to')) {
            info("Fetching logs between {$from->toDateTimeString()} and {$to->toDateTimeString()}");
        } else {
            info("Fetching logs since {$from->toDateTimeString()}");
        }

        [$logs, $rateLimited] = spin(
            fn () => $this->fetchLogs($environment->id, $from, $to, $limit),
            'Fetching logs...',
        );

        if ($rateLimited) {
            $this->outputWarning('The API rate limited the request after '.count($logs).' lines. Showing the lines fetched so far.');
        }

        if (empty($logs)) {
            $this->outputJsonIfWanted(['logs' => []]);

            warning('No logs found.');

            return self::FAILURE;
        }

        $tail = $this->option('tail');

        if ($tail && is_numeric($tail)) {
            $logs = array_slice($logs, -(int) $tail);
        }

        $this->outputJsonIfWanted($logs);

        (new EnvironmentLogsPrompt(
            logs: $logs,
            live: (bool) $this->option('live'),
            fetchLogs: fn (string $fetchFrom, string $fetchTo) => $this->client->environments()->logs($environment->id, $fetchFrom, $fetchTo),
            from: $from,
            to: $to,
        ))->display();
    }

    /**
     * Without a limit only the first page is fetched, matching the API's default.
     *
     * @return array{0: array<int, EnvironmentLog>, 1: bool}
     */
    protected function fetchLogs(string $environmentId, CarbonImmutable $from, CarbonImmutable $to, ?int $limit): array
    {
        $pages = [];
        $count = 0;
        $cursor = null;

        do {
            try {
                $page = $this->client->environments()->logsPage($environmentId, $from, $to, $cursor);
            } catch (TooManyRequestsException $e) {
                if ($pages === []) {
                    throw $e;
                }

                return [$this->mergePages($pages, $limit), true];
            }

            $pages[] = $page->logs;
            $count += count($page->logs);
            $cursor = $page->cursor;
        } while ($limit !== null && $count < $limit && $page->hasMore());

        return [$this->mergePages($pages, $limit), false];
    }

    /**
     * Each page walks further back in time but is itself oldest-first, so the
     * pages are reversed to keep the combined output chronological.
     *
     * @param  array<int, array<int, EnvironmentLog>>  $pages
     * @return array<int, EnvironmentLog>
     */
    protected function mergePages(array $pages, ?int $limit): array
    {
        $logs = array_merge(...array_reverse($pages));

        return $limit === null ? $logs : array_slice($logs, -$limit);
    }

    protected function resolveLimit(): ?int
    {
        $limit = $this->option('limit');

        if ($limit === null) {
            return null;
        }

        if (! ctype_digit((string) $limit) || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            $this->failAndExit('The --limit option must be a whole number between 1 and '.self::MAX_LIMIT.'.');
        }

        return (int) $limit;
    }

    protected function resolveFrom(): CarbonImmutable
    {
        if ($this->option('from')) {
            return CarbonImmutable::parse($this->option('from'));
        }

        $now = CarbonImmutable::now();

        if ($this->option('days')) {
            return $now->subDays($this->option('days'));
        }

        if ($this->option('hours')) {
            return $now->subHours($this->option('hours'));
        }

        if ($this->option('minutes')) {
            return $now->subMinutes($this->option('minutes'));
        }

        return $now->subDay();
    }

    protected function resolveTo(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->option('to') ?? CarbonImmutable::now());
    }
}
