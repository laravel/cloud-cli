<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;
use App\Concerns\RequiresEnvironment;
use App\Enums\LogLevel;
use App\Enums\LogType;
use Carbon\CarbonInterval;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\spin;

class EnvironmentLogs extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;
    use RequiresEnvironment;

    protected $signature = 'environment:logs
                            {application? : The application ID or name}
                            {environment? : The name of the environment}
                            {--from= : Start time for filtering logs}
                            {--to= : End time for filtering logs}
                            {--json : Output as JSON}
                            {--tail= : Number of lines to show from the end}
                            {--live : Live log output}';

    protected $description = 'View environment logs';

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Logs');

        $app = $this->getCloudApplication();
        $environments = spin(
            fn () => $this->client->listEnvironments($app->id),
            'Fetching environments...',
        );
        $environment = $this->getEnvironment(collect($environments->data));

        $from = $this->option('from') ?? now()->subDays(1)->toIso8601String();
        $to = $this->option('to') ?? now()->toIso8601String();

        $logsResponse = spin(
            fn () => $this->client->getEnvironmentLogs($environment->id, $from, $to),
            'Fetching logs...',
        );

        if ($this->option('json')) {
            $this->line($logsResponse->toJson());

            return;
        }

        if (empty($logsResponse->data)) {
            info('No logs found.');

            return;
        }

        $logs = $logsResponse->data;
        $tail = $this->option('tail');

        if ($tail && is_numeric($tail)) {
            $logs = array_slice($logs, -(int) $tail);
        }

        $this->newLine();

        // foreach ($logs as $log) {
        //     $this->line((string) $log);
        // }

        if ($this->option('live')) {
            $this->liveLogs($environment->id, $from, $to);
        }
    }

    protected function liveLogs(string $environmentId, string $from, string $to): void
    {
        $lastSeenCount = 0;

        while (true) {
            Sleep::for(CarbonInterval::seconds(3));

            $logsResponse = $this->client->getEnvironmentLogs($environmentId, $from, $to);
            $logs = $logsResponse->data;

            foreach ($logs as $log) {
                $timestamp = $log->loggedAt->format('Y-m-d H:i:s');
                $level = strtoupper($log->level->value);
                $levelColor = match ($log->level) {
                    LogLevel::INFO => 'green',
                    LogLevel::WARNING => 'yellow',
                    LogLevel::ERROR => 'red',
                    LogLevel::DEBUG => 'blue',
                };
                $type = $log->type->value;
                $typeColor = match ($log->type) {
                    LogType::ACCESS => 'cyan',
                    LogType::APPLICATION => 'green',
                    LogType::EXCEPTION => 'red',
                    LogType::SYSTEM => 'blue',
                };

                $output = $this->dim("[{$timestamp}]").' '.$this->{$levelColor}($level).' '.$this->{$typeColor}($type)." {$log->message}";

                if ($log->isAccessLog() && $accessData = $log->getAccessData()) {
                    $output .= " | {$accessData['method']} {$accessData['path']} | {$accessData['status']} | {$accessData['duration_ms']}ms";
                }

                if ($log->isExceptionLog() && $exceptionData = $log->getExceptionData()) {
                    $output .= " | {$exceptionData['class']}";
                    if ($exceptionData['code']) {
                        $output .= " ({$exceptionData['code']})";
                    }
                }

                $this->line($output);
            }

            $from = $to;
            $to = now()->toIso8601String();
        }
    }
}
