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
                            {--live : Live log output}';

    protected $description = 'View environment logs';

    protected $aliases = ['env:logs'];

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Logs');

        $app = $this->resolvers()->application()->from($this->argument('application'));
        $environment = $this->resolvers()->environment()->withApplication($app)->from($this->argument('environment'));

        $from = $this->resolveFrom();
        $to = $this->resolveTo();

        if ($this->option('to')) {
            info("Fetching logs between {$from->toDateTimeString()} and {$to->toDateTimeString()}");
        } else {
            info("Fetching logs since {$from->toDateTimeString()}");
        }

        $logs = spin(
            fn () => $this->client->environments()->logs($environment->id, $from, $to),
            'Fetching logs...',
        );

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
