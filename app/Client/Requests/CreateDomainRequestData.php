<?php

namespace App\Client\Requests;

class CreateDomainRequestData extends RequestData
{
    public function __construct(
        public readonly string $environmentId,
        public readonly string $name,
        public readonly ?string $wwwRedirect = null,
        public readonly ?bool $wildcardEnabled = null,
        public readonly ?string $verificationMethod = null,
        public readonly ?bool $allowDowntime = null,
        public readonly ?string $cloudflareStrategy = null,
    ) {
        //
    }

    public function toRequestData(): array
    {
        return $this->filter([
            'name' => $this->name,
            'www_redirect' => $this->wwwRedirect,
            'wildcard_enabled' => $this->wildcardEnabled,
            'verification_method' => $this->verificationMethod,
            'allow_downtime' => $this->allowDowntime,
            'cloudflare_strategy' => $this->cloudflareStrategy,
        ]);
    }
}
