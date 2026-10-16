<?php

namespace App\Concerns;

use App\Client\Requests\BucketCorsSettingsData;
use App\Enums\CorsHttpMethod;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\text;

trait DefinesBucketCorsSettings
{
    /**
     * Field keys match the API's error keys so a validation error re-prompts the right field.
     *
     * @var array<string, string>
     */
    protected array $corsListFields = [
        'cors_settings.allowed_origins' => 'allowed-origins',
        'cors_settings.allowed_headers' => 'allowed-headers',
        'cors_settings.expose_headers' => 'expose-headers',
    ];

    protected function defineCorsFields(?array $current = null): void
    {
        foreach ($this->corsListFields as $key => $option) {
            $previous = $this->implodeCorsList($current[str($key)->after('.')->toString()] ?? null);

            $this->form()->define(
                $key,
                fn ($resolver) => $resolver->fromInput(
                    fn ($value) => text(
                        label: $resolver->label(),
                        default: $value ?? $previous ?? '',
                        hint: 'Comma-separated list',
                    ),
                )
                    // Optional, so leaving it out headless means "not set" rather than an error.
                    ->nonInteractively(fn () => ''),
                $option,
            )->setPreviousValue($previous);
        }

        $previousMethods = $current['allowed_methods'] ?? null;

        $this->form()->define(
            'cors_settings.allowed_methods',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => multiselect(
                    label: 'Allowed methods',
                    options: collect(CorsHttpMethod::cases())->mapWithKeys(fn (CorsHttpMethod $method) => [$method->value => $method->value])->all(),
                    default: $this->corsList($value) ?? $previousMethods ?? array_column(CorsHttpMethod::cases(), 'value'),
                    required: true,
                ),
            ),
            'allowed-methods',
        )->setPreviousValue($this->implodeCorsList($previousMethods));

        $previousMaxAge = $current['max_age_seconds'] ?? null;

        $this->form()->define(
            'cors_settings.max_age_seconds',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Max age (seconds)',
                    default: (string) ($value ?? $previousMaxAge ?? ''),
                    validate: fn ($v) => $v !== '' && (! ctype_digit($v) || (int) $v < 1) ? 'Max age must be a positive whole number' : null,
                    hint: 'How long browsers may cache the preflight response',
                ),
            ),
            'max-age-seconds',
        )->setPreviousValue($previousMaxAge === null ? null : (string) $previousMaxAge);
    }

    /**
     * @param  bool  $emptyClears  On update an empty list clears the setting; on create it just means "not set".
     */
    protected function corsSettingsFromForm(bool $emptyClears): BucketCorsSettingsData
    {
        $list = function (string $key) use ($emptyClears): ?array {
            $values = $this->corsList($this->form()->get($key));

            return $values === [] && ! $emptyClears ? null : $values;
        };

        $methods = $this->corsList($this->form()->get('cors_settings.allowed_methods'));
        $maxAge = $this->form()->get('cors_settings.max_age_seconds');

        return new BucketCorsSettingsData(
            allowedOrigins: $list('cors_settings.allowed_origins'),
            // The API requires at least one method, so an empty list means "leave as is".
            allowedMethods: $methods ? array_map('strtoupper', $methods) : null,
            allowedHeaders: $list('cors_settings.allowed_headers'),
            exposeHeaders: $list('cors_settings.expose_headers'),
            maxAgeSeconds: $maxAge === null || $maxAge === '' ? null : (int) $maxAge,
        );
    }

    protected function corsList(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $values), fn ($item) => $item !== ''));
    }

    protected function implodeCorsList(?array $values): ?string
    {
        return $values ? implode(',', $values) : null;
    }
}
