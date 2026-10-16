<?php

namespace App\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class Domain extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $hostnameStatus,
        public readonly string $sslStatus,
        public readonly string $originStatus,
        public readonly ?string $redirect = null,
        public readonly array $dnsRecords = [],
        public readonly ?array $wildcard = null,
        public readonly ?array $www = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $lastVerifiedAt = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?string $environmentId = null,
        public readonly ?string $stage = null,
        public readonly ?string $cloudflareStrategy = null,
        public readonly ?bool $downtime = null,
        public readonly bool $wildcardEnabled = false,
        public readonly ?string $actionRequired = null,
    ) {
        //
    }

    public function status(): string
    {
        return $this->hostnameStatus;
    }

    public function isPrimary(): bool
    {
        return $this->type === 'root';
    }

    public function verificationStatus(): ?string
    {
        return $this->hostnameStatus;
    }

    public function actionRequiredLabel(): ?string
    {
        return match ($this->actionRequired) {
            'add_txt_records' => 'Add the TXT records to verify ownership',
            'add_dns_records' => 'Add the DNS records to point the domain at Laravel Cloud',
            'failed' => 'Verification failed',
            default => $this->actionRequired,
        };
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $relationships = $data['relationships'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'name' => $attributes['name'] ?? '',
            'type' => $attributes['type'] ?? 'root',
            'hostnameStatus' => $attributes['hostname_status'] ?? 'pending',
            'sslStatus' => $attributes['ssl_status'] ?? 'pending',
            'originStatus' => $attributes['origin_status'] ?? 'pending',
            'redirect' => $attributes['redirect'] ?? null,
            'dnsRecords' => $attributes['dns_records'] ?? [],
            'wildcard' => $attributes['wildcard'] ?? null,
            'www' => $attributes['www'] ?? null,
            'lastVerifiedAt' => $attributes['last_verified_at'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
            'stage' => $attributes['stage'] ?? null,
            'cloudflareStrategy' => $attributes['cloudflare_strategy'] ?? null,
            'downtime' => $attributes['downtime'] ?? null,
            'wildcardEnabled' => $attributes['wildcard_enabled'] ?? false,
            'actionRequired' => $attributes['action_required'] ?? null,
        ];

        if (isset($relationships['environment']['data']['id'])) {
            $transformed['environmentId'] = $relationships['environment']['data']['id'];
        }

        return self::from($transformed);
    }
}
