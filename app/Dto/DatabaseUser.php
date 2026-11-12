<?php

namespace App\Dto;

use App\Dto\Transformers\MaskSensitiveValue;
use App\Enums\DatabaseUserStatus;
use App\Enums\DatabaseUserType;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class DatabaseUser extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        #[WithCast(EnumCast::class)]
        public readonly DatabaseUserType $type,
        #[WithCast(EnumCast::class)]
        public readonly DatabaseUserStatus $status,
        #[WithCast(DateTimeInterfaceCast::class, type: CarbonImmutable::class)]
        public readonly ?CarbonImmutable $createdAt = null,
        #[WithTransformer(MaskSensitiveValue::class)]
        public readonly ?string $password = null,
        #[DataCollectionOf(Database::class)]
        public readonly array $databases = [],
    ) {
        //
    }

    public static function createFromResponse(array $response): self
    {
        $data = $response['data'] ?? [];
        $included = $response['included'] ?? [];
        $attributes = $data['attributes'] ?? [];

        // List responses share one `included` array across every user, so the
        // relationship is the only thing tying a database to its own user.
        $databaseIds = collect($data['relationships']['databases']['data'] ?? [])->pluck('id');

        $databases = collect($included)
            ->filter(fn ($item) => $item['type'] === 'databaseSchemas' && $databaseIds->contains($item['id']))
            ->map(fn ($item) => Database::createFromResponse(['data' => $item])->toArray())
            ->values()
            ->toArray();

        return self::from([
            'id' => $data['id'],
            'username' => $attributes['username'],
            'type' => $attributes['type'],
            'status' => $attributes['status'],
            'createdAt' => $attributes['created_at'] ?? null,
            'password' => $attributes['password'] ?? null,
            'databases' => $databases,
        ]);
    }

    public function isSuperuser(): bool
    {
        return $this->type === DatabaseUserType::SUPERUSER;
    }
}
