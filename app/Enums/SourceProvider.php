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
     * Remote hosts that identify this provider. A self-hosted instance can be any host,
     * so it claims none and detection never lands on it.
     */
    public function hosts(): array
    {
        return match ($this) {
            self::GITHUB => ['github.com'],
            self::GITLAB => ['gitlab.com'],
            self::BITBUCKET => ['bitbucket.org'],
            self::GITLAB_SELF_HOSTED => [],
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
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
