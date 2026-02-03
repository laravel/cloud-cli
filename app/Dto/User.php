<?php

namespace App\Dto;

use App\Concerns\HasDescriptiveArray;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class User extends Data
{
    use HasDescriptiveArray;

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $avatarUrl = null,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $attributes = $data['attributes'] ?? [];

        $transformed = [
            'id' => $data['id'],
            'name' => $attributes['name'] ?? '',
            'email' => $attributes['email'] ?? '',
            'avatarUrl' => $attributes['avatar_url'] ?? null,
            'createdAt' => $attributes['created_at'] ?? null,
        ];

        return self::from($transformed);
    }
}
