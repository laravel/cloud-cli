<?php

namespace App\Commands\Concerns;

use App\Client\Requests\UpdateEnvironmentRequestData;
use App\Client\Requests\UpdateInstanceRequestData;
use App\Dto\Environment;
use Carbon\CarbonInterval;
use Illuminate\Support\Composer;
use Illuminate\Support\Sleep;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\number;
use function Laravel\Prompts\spin;

trait ConfiguresEnvironmentFeatures
{
    protected function collectOptionsToEnable(Environment $environment): void
    {
        $composer = new Composer(app('files'), getcwd());

        $enableOptions = [
            'scheduler' => 'Laravel Scheduler',
            'hibernation' => 'Hibernation',
            'database_cluster' => 'Database Cluster',
        ];

        $packages = [
            'inertiajs/inertia-laravel' => ['inertia_ssr' => 'Inertia SSR'],
            'laravel/octane' => ['octane' => 'Laravel Octane'],
            'laravel/reverb' => ['websocket_cluster' => 'Websocket Cluster'],
        ];

        foreach ($packages as $package => $options) {
            if ($composer->hasPackage($package)) {
                $enableOptions = array_merge($enableOptions, $options);
            }
        }

        $selectedOptions = multiselect(
            label: 'Enable any of the following features?',
            options: $enableOptions,
        );

        if (count($selectedOptions) === 0) {
            return;
        }

        $instanceParams = [];
        $environmentParams = [];

        $mapping = [
            'scheduler' => 'uses_scheduler',
            'octane' => 'uses_octane',
            'inertia_ssr' => 'uses_inertia_ssr',
            'hibernation' => 'uses_sleep_mode',
        ];

        foreach ($mapping as $option => $param) {
            if (in_array($option, $selectedOptions)) {
                $instanceParams[$param] = true;
            }
        }

        if ($instanceParams['uses_sleep_mode'] ?? false) {
            $hibernateFor = number(
                label: 'Hibernate after',
                default: '5',
                required: true,
                hint: 'The number of minutes without HTTP requests received before your application hibernates (1-60)',
                min: 1,
                max: 60,
            );

            $instanceParams['sleep_timeout'] = $hibernateFor;
        }

        if (in_array('database_cluster', $selectedOptions)) {
            $cluster = $this->getDatabaseCluster();

            if ($cluster) {
                $cluster = $this->client->databaseClusters()->include('schemas')->get($cluster->id);
                $database = $this->getDatabase($cluster);
                $environmentParams['database_schema_id'] = $database->id;
            }
        }

        if (in_array('websocket_cluster', $selectedOptions)) {
            $cluster = $this->getWebsocketCluster();

            if ($cluster) {
                $cluster = $this->client->websocketClusters()->get($cluster->id);
                $websocketApplication = $this->getWebSocketApplication($cluster);

                if ($websocketApplication) {
                    $environmentParams['websocket_application_id'] = $websocketApplication->id;
                }
            }
        }

        if (count($instanceParams) > 0) {
            $this->loopUntilValid(
                fn () => spin(
                    fn () => $this->client->instances()->update(new UpdateInstanceRequestData(
                        instanceId: $environment->instances[0],
                        usesScheduler: $instanceParams['uses_scheduler'] ?? null,
                        usesOctane: $instanceParams['uses_octane'] ?? null,
                        usesInertiaSsr: $instanceParams['uses_inertia_ssr'] ?? null,
                        usesSleepMode: $instanceParams['uses_sleep_mode'] ?? null,
                        sleepTimeout: isset($instanceParams['sleep_timeout']) ? (int) $instanceParams['sleep_timeout'] : null,
                    )),
                    'Updating instance...',
                ),
            );
        }

        if (count($environmentParams) > 0) {
            $this->loopUntilValid(
                function ($errors) use ($environmentParams, $environment) {
                    if ($errors->messageContains('global', 'wait a few seconds')) {
                        info('Waiting a few seconds...');
                        Sleep::for(CarbonInterval::seconds(5));
                    }

                    return spin(
                        fn () => $this->client->environments()->update(
                            new UpdateEnvironmentRequestData(
                                environmentId: $environment->id,
                                databaseSchemaId: $environmentParams['database_schema_id'] ?? null,
                                websocketApplicationId: $environmentParams['websocket_application_id'] ?? null,
                            ),
                        ),
                        'Updating environment...',
                    );
                },
                handleNonInteractiveErrors: function ($errors) {
                    if ($errors->messageContains('global', 'wait a few seconds')) {
                        Sleep::for(CarbonInterval::seconds(5));

                        return true;
                    }

                    return false;
                },
            );
        }
    }

    protected function applyOpinionatedOptions(Environment $environment): void
    {
        $composer = new Composer(app('files'), getcwd());

        $instanceParams = [
            'uses_scheduler' => true,
            'uses_sleep_mode' => false,
            // 'sleep_timeout' => 5,
            'uses_octane' => $composer->hasPackage('laravel/octane'),
        ];

        $environmentParams = [];

        $databaseSchemaId = $this->provisionDatabaseOpinionated();

        if ($databaseSchemaId !== null) {
            $environmentParams['database_schema_id'] = $databaseSchemaId;
        }

        if ($composer->hasPackage('laravel/reverb')) {
            $websocketAppId = $this->provisionWebsocketOpinionated();

            if ($websocketAppId !== null) {
                $environmentParams['websocket_application_id'] = $websocketAppId;
            }
        }

        $this->client->instances()->update(
            new UpdateInstanceRequestData(
                instanceId: $environment->instances[0],
                usesScheduler: $instanceParams['uses_scheduler'],
                usesOctane: $instanceParams['uses_octane'],
                usesInertiaSsr: null,
                usesSleepMode: $instanceParams['uses_sleep_mode'],
                // sleepTimeout: $instanceParams['sleep_timeout'],
            ),
        );

        if (count($environmentParams) > 0) {
            $this->loopUntilValid(
                function ($errors) use ($environmentParams, $environment) {
                    if ($errors->messageContains('database', 'please wait')) {
                        info('Waiting a few seconds...');
                        Sleep::for(CarbonInterval::seconds(5));
                    }

                    return spin(fn () => $this->client->environments()->update(
                        new UpdateEnvironmentRequestData(
                            environmentId: $environment->id,
                            databaseSchemaId: $environmentParams['database_schema_id'] ?? null,
                            websocketApplicationId: $environmentParams['websocket_application_id'] ?? null,
                        ),
                    ), 'Updating environment...');
                },
                handleNonInteractiveErrors: function ($errors) {
                    if ($errors->messageContains('database', 'please wait')) {
                        Sleep::for(CarbonInterval::seconds(5));

                        return true;
                    }

                    return false;
                },
            );
        }
    }
}
