<?php

namespace App\Concerns;

use App\Client\Requests\CreateWebSocketApplicationRequestData;
use App\Dto\WebsocketApplication;
use App\Dto\WebsocketCluster;

use function Laravel\Prompts\number;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

trait CreatesWebSocketApplication
{
    protected function createWebSocketApplication(WebsocketCluster $cluster, array $defaults = []): WebsocketApplication
    {
        $this->form()->prompt(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn (?string $value) => text(
                    label: 'Application name',
                    default: $value ?? $defaults['name'] ?? '',
                    required: true,
                ),
            ),
        );

        $this->form()->prompt(
            'allowed_origins',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn (?string $value) => textarea(
                        label: 'Allowed origins',
                        default: $value ?? $defaults['allowed_origins'] ?? '',
                        hint: 'Origins that are allowed to connect to the application, separated by new lines, prefixed with the protocol (https://)',
                    ),
                )
                ->nonInteractively(fn () => $defaults['allowed_origins'] ?? null),
        );

        $this->form()->prompt(
            'ping_interval',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn ($value) => number(
                        label: 'Ping interval',
                        default: $value ?? $defaults['ping_interval'] ?? 60,
                        min: 1,
                        max: 60,
                        required: true,
                    ),
                )
                ->nonInteractively(fn () => $defaults['ping_interval'] ?? 60),
        );

        $this->form()->prompt(
            'activity_timeout',
            fn ($resolver) => $resolver
                ->fromInput(
                    fn ($value) => number(
                        label: 'Activity timeout',
                        default: $value ?? $defaults['activity_timeout'] ?? 30,
                        min: 1,
                        max: 60,
                        required: true,
                    ),
                )
                ->nonInteractively(fn () => $defaults['activity_timeout'] ?? 30),
        );

        $allowedOrigins = $this->form()->get('allowed_origins');

        if ($allowedOrigins !== null && $allowedOrigins !== '') {
            $separator = str_contains($allowedOrigins, PHP_EOL) ? PHP_EOL : ',';
            $allowedOrigins = collect(explode($separator, $allowedOrigins))->map(fn ($origin) => trim($origin))->filter(fn ($origin) => $origin !== '')->values()->toArray();
        } else {
            $allowedOrigins = null;
        }

        return spin(
            fn () => $this->client->websocketApplications()->create(
                new CreateWebSocketApplicationRequestData(
                    clusterId: $cluster->id,
                    name: $this->form()->get('name'),
                    pingInterval: $this->form()->get('ping_interval'),
                    activityTimeout: $this->form()->get('activity_timeout'),
                    allowedOrigins: $allowedOrigins,
                ),
            ),
            'Creating WebSocket application...',
        );
    }

    protected function createWebSocketApplicationWithOptions(WebsocketCluster $cluster, array $options): WebsocketApplication
    {
        $name = $options['name'] ?? '';
        $pingInterval = (int) ($options['ping_interval'] ?? 60);
        $activityTimeout = (int) ($options['activity_timeout'] ?? 30);
        $allowedOrigins = $options['allowed_origins'] ?? null;

        if (is_array($allowedOrigins)) {
            $allowedOrigins = array_values(array_filter($allowedOrigins));
        } elseif (is_string($allowedOrigins) && $allowedOrigins !== '') {
            $allowedOrigins = collect(explode(PHP_EOL, $allowedOrigins))->filter(fn ($origin) => $origin !== '')->values()->toArray();
        } else {
            $allowedOrigins = null;
        }

        return spin(
            fn () => $this->client->websocketApplications()->create(
                new CreateWebSocketApplicationRequestData(
                    clusterId: $cluster->id,
                    name: $name,
                    pingInterval: $pingInterval,
                    activityTimeout: $activityTimeout,
                    allowedOrigins: $allowedOrigins,
                ),
            ),
            'Creating WebSocket application...',
        );
    }
}
