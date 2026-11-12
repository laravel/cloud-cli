<?php

namespace App\Enums;

enum DatabaseUserType: string
{
    case SUPERUSER = 'superuser';
    case READ_WRITE = 'read_write';
    case READ_ONLY = 'read_only';

    public function label(): string
    {
        return match ($this) {
            self::SUPERUSER => 'Superuser',
            self::READ_WRITE => 'Read and write',
            self::READ_ONLY => 'Read only',
        };
    }

    /**
     * The types a user can be given. The API creates the superuser itself.
     *
     * @return array<string, string>
     */
    public static function assignableOptions(): array
    {
        return collect([self::READ_WRITE, self::READ_ONLY])
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->toArray();
    }
}
