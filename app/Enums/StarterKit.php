<?php

namespace App\Enums;

/**
 * Mirrors the API's StarterKit, so the case value is what we send.
 */
enum StarterKit: string
{
    case LARAVEL = 'laravel';
    case LARAVEL_REACT = 'laravel-react';
    case LARAVEL_VUE = 'laravel-vue';
    case LARAVEL_LIVEWIRE = 'laravel-livewire';
    case LARAVEL_SVELTE = 'laravel-svelte';
    case NEXTJS = 'nextjs';
    case NUXT = 'nuxt';
    case EXPRESS = 'express';
    case GO = 'go';
    case DJANGO = 'django';
    case FASTAPI = 'fastapi';
    case RAILS = 'rails';

    public function label(): string
    {
        return match ($this) {
            self::LARAVEL => 'Laravel',
            self::LARAVEL_REACT => 'Laravel + React',
            self::LARAVEL_VUE => 'Laravel + Vue',
            self::LARAVEL_LIVEWIRE => 'Laravel + Livewire',
            self::LARAVEL_SVELTE => 'Laravel + Svelte',
            self::NEXTJS => 'Next.js',
            self::NUXT => 'Nuxt',
            self::EXPRESS => 'Express',
            self::GO => 'Go',
            self::DJANGO => 'Django',
            self::FASTAPI => 'FastAPI',
            self::RAILS => 'Rails',
        };
    }

    public function isLaravel(): bool
    {
        return str_starts_with($this->value, 'laravel');
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
