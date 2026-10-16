<?php

namespace App\Enums;

enum CacheEvictionPolicy: string
{
    case ALLKEYS_LRU = 'allkeys-lru';
    case NOEVICTION = 'noeviction';
    case VOLATILE_LRU = 'volatile-lru';
    case ALLKEYS_RANDOM = 'allkeys-random';
    case VOLATILE_RANDOM = 'volatile-random';
    case VOLATILE_TTL = 'volatile-ttl';
    case ALLKEYS_LFU = 'allkeys-lfu';
    case VOLATILE_LFU = 'volatile-lfu';

    public function label(): string
    {
        return match ($this) {
            self::ALLKEYS_LRU => 'All keys LRU',
            self::NOEVICTION => 'No eviction',
            self::VOLATILE_LRU => 'Volatile LRU',
            self::ALLKEYS_RANDOM => 'All keys random',
            self::VOLATILE_RANDOM => 'Volatile random',
            self::VOLATILE_TTL => 'Volatile TTL',
            self::ALLKEYS_LFU => 'All keys LFU',
            self::VOLATILE_LFU => 'Volatile LFU',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->toArray();
    }
}
