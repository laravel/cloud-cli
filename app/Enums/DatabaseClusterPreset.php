<?php

namespace App\Enums;

use Closure;

enum DatabaseClusterPreset: string
{
    case LaravelMysql8 = 'laravel_mysql_8';
    case NeonServerlessPostgres18 = 'neon_serverless_postgres_18';
    case NeonServerlessPostgres17 = 'neon_serverless_postgres_17';

    public function presets(): array
    {
        return match ($this) {
            self::LaravelMysql8 => [
                'Dev' => [
                    'size' => 'db-flex.m-1vcpu-512mb',
                    'storage' => 5,
                    'retention_days' => 1,
                    'uses_scheduled_snapshots' => false,
                    'is_public' => false,
                ],
                'Prod' => [
                    'size' => 'db-flex.m-1vcpu-2gb',
                    'storage' => 20,
                    'retention_days' => 7,
                    'uses_scheduled_snapshots' => false,
                    'is_public' => false,
                ],
                'Scale' => [
                    'size' => 'db-pro.m-4vcpu-16gb',
                    'storage' => 200,
                    'retention_days' => 14,
                    'uses_scheduled_snapshots' => false,
                    'is_public' => false,
                ],
            ],
            self::NeonServerlessPostgres18 => [
                'Dev' => [
                    'cu_min' => 0.25,
                    'cu_max' => 0.25,
                    'suspend_seconds' => 300,
                    'retention_days' => 0,
                ],
                'Prod' => [
                    'cu_min' => 0.25,
                    'cu_max' => 1,
                    'suspend_seconds' => 0,
                    'retention_days' => 7,
                ],
                'Scale' => [
                    'cu_min' => 1,
                    'cu_max' => 4,
                    'suspend_seconds' => 0,
                    'retention_days' => 14,
                ],
            ],
            self::NeonServerlessPostgres17 => [
                'Dev' => [
                    'cu_min' => 0.25,
                    'cu_max' => 0.25,
                    'suspend_seconds' => 300,
                    'retention_days' => 0,
                ],
                'Prod' => [
                    'cu_min' => 0.25,
                    'cu_max' => 1,
                    'suspend_seconds' => 0,
                    'retention_days' => 7,
                ],
                'Scale' => [
                    'cu_min' => 1,
                    'cu_max' => 4,
                    'suspend_seconds' => 0,
                    'retention_days' => 14,
                ],
            ],
        };
    }

    public function description(): Closure
    {
        return match ($this) {
            self::LaravelMysql8 => fn ($preset) => sprintf(
                '%s · %sGB storage · %d %s backups',
                str($preset['size'])
                    ->replaceMatches(
                        '/^db-(pro|flex)\.(m|c|g)-(\d+)vcpu-(\d+)(gb|mb)$/',
                        '$1 ($3 vCPU · $4 $5 RAM)',
                    )
                    ->replace('gb', 'GiB')
                    ->replace('mb', 'MiB')
                    ->ucfirst()
                    ->toString(),
                $preset['storage'],
                $preset['retention_days'],
                str('day')->plural($preset['retention_days']),
            ),
            self::NeonServerlessPostgres18 => fn ($preset) => sprintf(
                '%s vCPU units · %s · %s',
                $preset['cu_min'] === $preset['cu_max'] ? $this->formatNumber($preset['cu_min']) : $this->formatNumber($preset['cu_min']).' – '.$this->formatNumber($preset['cu_max']),
                $preset['suspend_seconds'] > 0 ? 'Hibernate after '.$preset['suspend_seconds'].' seconds' : 'No hibernation',
                $preset['retention_days'] === 0 ? 'No backups' : $preset['retention_days'].' days PITR',
            ),
            self::NeonServerlessPostgres17 => fn ($preset) => sprintf(
                '%s vCPU units · %s · %s',
                $preset['cu_min'] === $preset['cu_max'] ? $this->formatNumber($preset['cu_min']) : $this->formatNumber($preset['cu_min']).' – '.$this->formatNumber($preset['cu_max']),
                $preset['suspend_seconds'] === 0 ? 'No hibernation' : 'Hibernate after '.$preset['suspend_seconds'].' seconds',
                $preset['retention_days'] === 0 ? 'No backups' : $preset['retention_days'].' days PITR',
            ),
        };
    }

    protected function formatNumber(string $number): string
    {
        return match ($number) {
            '0.25' => '¼',
            '0.5' => '½',
            default => $number,
        };
    }
}
