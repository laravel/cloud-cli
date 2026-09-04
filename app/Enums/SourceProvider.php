<?php

namespace App\Enums;

/**
 * Mirrors the API's SourceControlProviderType, so the case value is what we send.
 */
enum SourceProvider: string
{
    case GITHUB = 'github';
    case GITLAB = 'gitlab';
    case GITLAB_SELF_HOSTED = 'gitlab_self_hosted';
    case BITBUCKET = 'bitbucket';

    public function label(): string
    {
        return match ($this) {
            self::GITHUB => 'GitHub',
            self::GITLAB => 'GitLab',
            self::GITLAB_SELF_HOSTED => 'GitLab (self-hosted)',
            self::BITBUCKET => 'Bitbucket',
        };
    }

    /**
     * Whether the CLI has a driver for this provider. Unsupported cases still exist so
     * the enum matches the API, and so --source-provider fails with a real message.
     */
    public function supported(): bool
    {
        return $this !== self::BITBUCKET;
    }

    /**
     * Remote hosts that identify this provider. Self-hosted and unsupported providers
     * claim none, so detection never lands on them.
     */
    public function hosts(): array
    {
        return match ($this) {
            self::GITHUB => ['github.com'],
            self::GITLAB => ['gitlab.com'],
            self::GITLAB_SELF_HOSTED, self::BITBUCKET => [],
        };
    }

    public static function fromHost(?string $host): ?self
    {
        if ($host === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if (in_array($host, $case->hosts(), true)) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->filter(fn (self $case) => $case->supported())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
