<?php

namespace App\Commands;

use App\Client\Requests\UpdateWebSocketApplicationRequestData;
use App\Dto\WebsocketApplication;
use App\Exceptions\CommandExitException;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class WebsocketApplicationUpdate extends BaseCommand
{
    protected ?string $jsonDataClass = WebsocketApplication::class;

    protected $signature = 'websocket-application:update
                            {application? : The application ID or name}
                            {--name= : Application name}
                            {--force : Force update without confirmation}';

    protected $description = 'Update a WebSocket application';

    protected $aliases = ['ws-app:update'];

    public function handle()
    {
        $this->ensureClient();

        intro('Updating WebSocket Application');

        $app = $this->resolvers()->websocketApplication()->from($this->argument('application'));

        $this->defineFields($app);

        foreach ($this->form()->filled() as $resolver) {
            $this->reportChange(
                $resolver->label(),
                $resolver->previousValue(),
                $resolver->value(),
            );
        }

        $updatedApp = $this->runUpdate(
            fn () => $this->updateApplication($app),
            fn () => $this->collectDataAndUpdate($app),
        );

        $this->outputJsonIfWanted($updatedApp);

        success("WebSocket application updated: {$updatedApp->name}");
    }

    protected function updateApplication(WebsocketApplication $app): WebsocketApplication
    {
        spin(
            fn () => $this->client->websocketApplications()->update(
                new UpdateWebSocketApplicationRequestData(
                    applicationId: $app->id,
                    name: $this->form()->get('name'),
                ),
            ),
            'Updating WebSocket application...',
        );

        return $this->client->websocketApplications()->get($app->id);
    }

    protected function defineFields(WebsocketApplication $app): void
    {
        $this->form()->define(
            'name',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => text(
                    label: 'Application name',
                    required: true,
                    default: $value ?? $app->name,
                ),
            ),
        )->setPreviousValue($app->name);

        $this->form()->define(
            'allowed_origins',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => textarea(
                    label: 'Allowed origins',
                    default: $value ?? implode(PHP_EOL, $app->allowedOrigins ?? []),
                    hint: 'Origins that are allowed to connect to the application, separated by new lines, prefixed with the protocol (https://)',
                ),
            ),
        )->setPreviousValue($app->allowedOrigins ? implode(PHP_EOL, $app->allowedOrigins) : null);

        $this->form()->define(
            'ping_interval',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Ping interval',
                    default: $value ?? $app->pingInterval,
                    min: 1,
                    max: 60,
                    required: true,
                ),
            ),
        )->setPreviousValue($app->pingInterval);

        $this->form()->define(
            'activity_timeout',
            fn ($resolver) => $resolver->fromInput(
                fn ($value) => number(
                    label: 'Activity timeout',
                    default: $value ?? $app->activityTimeout,
                    min: 1,
                    max: 60,
                    required: true,
                ),
            ),
        )->setPreviousValue($app->activityTimeout);
    }

    protected function collectDataAndUpdate(WebsocketApplication $app): WebsocketApplication
    {
        $selection = multiselect(
            label: 'What do you want to update?',
            options: collect($this->form()->defined())->mapWithKeys(fn ($field, $key) => [
                $field->key => $field->label(),
            ])->toArray(),
        );

        if (empty($selection)) {
            $this->outputErrorOrThrow('No fields to update. Select at least one option.');

            throw new CommandExitException(self::FAILURE);
        }

        foreach ($selection as $optionName) {
            $this->form()->prompt($optionName);
        }

        return $this->updateApplication($app);
    }
}
