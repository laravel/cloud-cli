<?php

namespace App\Dto;

use App\Enums\LogLevel;
use App\Enums\LogType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class EnvironmentLog extends Data
{
    public function __construct(
        public readonly string $message,
        #[WithCast(EnumCast::class)]
        public readonly LogLevel $level,
        #[WithCast(EnumCast::class)]
        public readonly LogType $type,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly CarbonImmutable $loggedAt,
        public readonly ?array $data = null,
    ) {
        //
    }

    public function isAccessLog(): bool
    {
        return $this->type === LogType::ACCESS;
    }

    public function isApplicationLog(): bool
    {
        return $this->type === LogType::APPLICATION;
    }

    public function isExceptionLog(): bool
    {
        return $this->type === LogType::EXCEPTION;
    }

    public function isSystemLog(): bool
    {
        return $this->type === LogType::SYSTEM;
    }

    public function getAccessData(): ?array
    {
        if (! $this->isAccessLog() || ! is_array($this->data)) {
            return null;
        }

        return [
            'status' => $this->data['status'] ?? null,
            'method' => $this->data['method'] ?? null,
            'path' => $this->data['path'] ?? null,
            'duration_ms' => $this->data['duration_ms'] ?? null,
            'bytes_sent' => $this->data['bytes_sent'] ?? null,
            'ip' => $this->data['ip'] ?? null,
            'user_agent' => $this->data['user_agent'] ?? null,
            'country' => $this->data['country'] ?? null,
        ];
    }

    public function getApplicationData(): ?array
    {
        if (! $this->isApplicationLog() || ! is_array($this->data)) {
            return null;
        }

        return [
            'channel' => $this->data['channel'] ?? null,
            'context' => $this->data['context'] ?? null,
            'extra' => $this->data['extra'] ?? null,
        ];
    }

    public function getExceptionData(): ?array
    {
        if (! $this->isExceptionLog() || ! is_array($this->data)) {
            return null;
        }

        return [
            'class' => $this->data['class'] ?? null,
            'code' => $this->data['code'] ?? null,
            'file' => $this->data['file'] ?? null,
            'trace' => $this->data['trace'] ?? null,
        ];
    }

    public function __toString(): string
    {
        $timestamp = $this->loggedAt->format('Y-m-d H:i:s');
        $level = strtoupper($this->level->value);
        $type = $this->type->value;

        $output = "[{$timestamp}] [{$level}] [{$type}] {$this->message}";

        if ($this->isAccessLog() && $accessData = $this->getAccessData()) {
            $output .= " | {$accessData['method']} {$accessData['path']} | {$accessData['status']} | {$accessData['duration_ms']}ms";
        }

        if ($this->isExceptionLog() && $exceptionData = $this->getExceptionData()) {
            $output .= " | {$exceptionData['class']}";
            if ($exceptionData['code']) {
                $output .= " ({$exceptionData['code']})";
            }
        }

        return $output;
    }

    public static function fromJsonApi(array $response): self
    {
        $data = $response;

        return self::from([
            'message' => $data['message'] ?? '',
            'level' => $data['level'] ?? 'info',
            'type' => $data['type'] ?? 'application',
            'loggedAt' => $data['logged_at'] ?? null,
            'data' => $data['data'] ?? null,
        ]);
    }
}
