<?php

namespace App\Commands\Concerns;

use App\Client\Requests\AddEnvironmentVariablesRequestData;
use App\Dto\Application;
use Carbon\CarbonInterval;
use Dotenv\Dotenv;
use Illuminate\Support\Sleep;
use Throwable;

use function Laravel\Prompts\multiselect;

trait ImportsEnvironmentVariables
{
    protected function pushCustomEnvironmentVariables(Application $application): void
    {
        $envPath = getcwd().'/.env';

        if (! file_exists($envPath)) {
            return;
        }

        try {
            $variables = Dotenv::parse(file_get_contents($envPath));
        } catch (Throwable $e) {
            return;
        }

        $diff = array_diff(array_keys($variables), config('env.laravel'));

        if (count($diff) === 0) {
            return;
        }

        $varOptions = collect($diff)->mapWithKeys(fn ($key) => [
            $key => $key.$this->dim(str($variables[$key])->limit(5)->prepend(' (')->append(')')),
        ]);

        $varsToAdd = multiselect(
            label: 'Add local environment variables to Cloud environment?',
            options: $varOptions->toArray(),
        );

        if (count($varsToAdd) === 0) {
            return;
        }

        $varsToAdd = collect($varsToAdd)->map(fn ($key) => ['key' => $key, 'value' => $variables[$key]]);

        dynamicSpinner(
            function () use ($application, $varsToAdd) {
                while (count($application->environmentIds) === 0) {
                    $application = $this->client->applications()->withDefaultIncludes()->get($application->id);
                    Sleep::for(CarbonInterval::seconds(1));
                }

                $this->client->environments()->addVariables(
                    new AddEnvironmentVariablesRequestData(
                        environmentId: $application->environmentIds[0],
                        variables: $varsToAdd->toArray(),
                    ),
                );
            },
            'Adding selected variables to Cloud environment',
        );
    }
}
