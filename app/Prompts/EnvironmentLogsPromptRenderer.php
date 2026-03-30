<?php

namespace App\Prompts;

use App\Concerns\CapturesOutput;
use App\Concerns\DrawsThemeBoxes;
use App\Dto\EnvironmentLog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Laravel\Prompts\Themes\Default\Concerns\InteractsWithStrings;
use Throwable;

class EnvironmentLogsPromptRenderer extends Renderer
{
    use CapturesOutput;
    use DrawsThemeBoxes;
    use InteractsWithStrings;

    /**
     * @var array<string>
     */
    protected array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    public function __invoke(EnvironmentLogsPrompt $prompt): string
    {
        $output = $this->captureOutput(function () use ($prompt) {
            foreach ($prompt->logs as $log) {
                $this->writeLog($log);
            }
        });

        if ($output !== '') {
            $prompt->renderDirectly($output);
        }

        if (! $prompt->live) {
            return $this;
        }

        $checkingIn = $prompt->loopStartedAt === null
            ? $prompt->checkEvery
            : ($prompt->lastCheck === null
                ? max(0, $prompt->checkEvery - (int) $prompt->loopStartedAt->diffInSeconds(CarbonImmutable::now()))
                : max(0, $prompt->checkEvery - (int) $prompt->lastCheck->diffInSeconds(CarbonImmutable::now())));

        $message = $this->dim(
            'Checking for logs in '.
                CarbonInterval::seconds($checkingIn)->format('%I:%S'),
        );

        $frame = $this->frames[$prompt->count % count($this->frames)];

        if ($checkingIn < 1) {
            $message .= ' '.$this->cyan($frame).' '.($prompt->fade ? $this->dim('Checking for logs...') : 'Checking for logs...');
            $prompt->fade = false;
        } elseif (! $prompt->fade) {
            $message .= ' '.$this->cyan($frame).' '.$this->dim('Checking for logs...');
            $prompt->fade = true;
        }

        $this->lineWithBorder($message);

        return (string) $this;
    }

    public function writeLog(EnvironmentLog $log): void
    {
        $timestamp = $log->loggedAt->toDateTimeString();

        $level = $log->level->label();
        $levelColor = $log->level->color();

        $type = $log->type->label();
        $typeColor = $log->type->color();

        $params = $this->getBoxParams($log);

        $this->box(
            title: $this->dim("{$timestamp}").' '.$this->{$levelColor}($level).$this->dim(' ['.$this->{$typeColor}($type).']'),
            body: $params['body'] ?? '',
            info: $params['info'] ?? '',
            symbol: null,
            footer: $params['footer'] ?? '',
        );
    }

    /**
     * @return array{title?: string, body?: string, info?: string, footer?: string}
     */
    protected function getBoxParams(EnvironmentLog $log): array
    {
        if ($log->isAccessLog() && $accessData = $log->getAccessData()) {
            return [
                'info' => $this->getFooter(
                    $accessData['method'].' '.$accessData['path'],
                    $accessData['status'] === 0 ? null : $accessData['status'],
                    ($accessData['duration_ms'] ?? 0).'ms',
                ),
            ];
        }

        if ($log->isSystemLog()) {
            return ['body' => $this->getBody($log->message)];
        }

        if ($log->isExceptionLog() && $exceptionData = $log->getExceptionData()) {
            $body = $this->getBody($log->message);
            $info = $exceptionData['class'] ?? '';

            if ($exceptionData['code']) {
                $info .= " ({$exceptionData['code']})";
            }

            $info = trim($info);

            return ['body' => $body, 'info' => $info];
        }

        if ($log->isApplicationLog()) {
            $json = $this->tryToDecodeJson($log->message);

            if ($json !== null) {
                $body = $this->getBody($json['message'] ?? '');

                $footer = [];

                if ($json['context']) {
                    $footer[] = $this->dim('Context');
                    $footer[] = $this->getBody(json_encode($json['context']));
                }

                if ($json['extra']) {
                    $footer[] = $this->dim('Extra');
                    $footer[] = $this->getBody(json_encode($json['extra']));
                }

                $footer = implode(PHP_EOL, $footer);
                $info = $this->getFooter($json['channel']);

                return ['body' => $body, 'info' => $info, 'footer' => $footer];
            }

            return ['body' => $this->getBody($log->message)];
        }

        return [];
    }

    protected function getFooter(...$items): string
    {
        return implode(' · ', array_filter($items));
    }

    protected function getBody(string $message): string
    {
        return $this->mbWordwrap(
            string: $this->getPotentialJsonMessage($message),
            width: $this->prompt->terminal()->cols() - 10,
            cut_long_words: true,
        );
    }

    protected function getPotentialJsonMessage(string $message): string
    {
        $decoded = $this->tryToDecodeJson($message);

        if ($decoded !== null) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $message;
    }

    protected function tryToDecodeJson(string $message): ?array
    {
        try {
            return json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return null;
        }
    }
}
