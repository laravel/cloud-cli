<?php

namespace App\Commands;

use App\Prompts\EnvironmentLogsPrompt;
use Carbon\CarbonImmutable;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class EnvironmentLogs extends BaseCommand
{
    protected $signature = 'environment:logs
                            {application? : The application ID or name}
                            {environment? : The name or ID of the environment}
                            {--from= : Start time for filtering logs}
                            {--days= : Number of days to fetch logs}
                            {--hours= : Number of hours to fetch logs}
                            {--minutes= : Number of minutes to fetch logs}
                            {--to= : End time for filtering logs}
                            {--tail= : Number of lines to show from the end}
                            {--type= : Log type (all, application, access)}
                            {--query= : Only show logs containing this text}
                            {--case-sensitive : Match --query case-sensitively}
                            {--whole-word : Match --query as a whole word}
                            {--instance=* : Only show logs from these instance IDs}
                            {--cursor= : Cursor from a previous run, to fetch the next page of logs}
                            {--live : Live log output}';

    /**
     * @var array<int, string>
     */
    protected array $types = ['all', 'application', 'access'];

    protected $description = 'View environment logs';

    protected $aliases = ['env:logs'];

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Logs');

        $app = $this->resolvers()->application()->from($this->argument('application'));
        $environment = $this->resolvers()->environment()->withApplication($app)->from($this->argument('environment'));

        $type = $this->option('type');

        if ($type !== null && ! in_array($type, $this->types, true)) {
            $this->failAndExit("Invalid --type value '{$type}'. Must be one of: ".implode(', ', $this->types).'.');
        }

        $from = $this->resolveFrom();
        $to = $this->resolveTo();

        if ($this->option('to')) {
            info("Fetching logs between {$from->toDateTimeString()} and {$to->toDateTimeString()}");
        } else {
            info("Fetching logs since {$from->toDateTimeString()}");
        }

        $fetchLogs = fn (string $fetchFrom, string $fetchTo, ?string $cursor = null) => $this->client->environments()->logs(
            environmentId: $environment->id,
            from: $fetchFrom,
            to: $fetchTo,
            cursor: $cursor,
            type: $type,
            query: $this->option('query'),
            caseSensitive: $this->option('case-sensitive') ?: null,
            wholeWord: $this->option('whole-word') ?: null,
            instances: $this->option('instance'),
        );

        $page = spin(
            fn () => $fetchLogs($from->toIso8601String(), $to->toIso8601String(), $this->option('cursor')),
            'Fetching logs...',
        );

        $logs = $page->logs;

        if (empty($logs)) {
            $this->outputJsonIfWanted(['logs' => [], 'cursor' => $page->cursor]);

            warning('No logs found.');

            return self::FAILURE;
        }

        $tail = $this->option('tail');

        if ($tail && is_numeric($tail)) {
            $logs = array_slice($logs, -(int) $tail);
        }

        $this->outputJsonIfWanted(['logs' => $logs, 'cursor' => $page->cursor]);

        (new EnvironmentLogsPrompt(
            logs: $logs,
            live: (bool) $this->option('live'),
            fetchLogs: fn (string $fetchFrom, string $fetchTo) => $fetchLogs($fetchFrom, $fetchTo)->logs,
            from: $from,
            to: $to,
        ))->display();

        if ($page->cursor !== null && ! $this->option('live')) {
            // The default --from and --to move with the clock, so the hint pins them to this run's window.
            info("More logs may exist. Fetch the next page with --from={$from->toIso8601String()} --to={$to->toIso8601String()} --cursor={$page->cursor}");
        }
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
