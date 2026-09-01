<?php

namespace App\Dto;

use RuntimeException;
use SensitiveParameter;
use Spatie\LaravelData\Data;

class SecretPublicKey extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $publicKey,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        return self::from([
            'id' => $data['id'],
            'publicKey' => $attributes['public_key'] ?? '',
        ]);
    }

    /**
     * Encrypt a value so only Cloud can decrypt it. Plaintext values are rejected by the API.
     */
    public function encrypt(#[SensitiveParameter] string $value): string
    {
        if (! function_exists('sodium_crypto_box_seal')) {
            throw new RuntimeException('The sodium extension is required to encrypt secrets. Install or enable ext-sodium for your PHP installation.');
        }

        $publicKey = base64_decode($this->publicKey, strict: true);

        if ($publicKey === false) {
            throw new RuntimeException('The secrets public key returned by Cloud is not valid base64.');
        }

        return base64_encode(sodium_crypto_box_seal($value, $publicKey));
    }
}
