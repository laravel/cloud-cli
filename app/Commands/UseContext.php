<?php

namespace App\Commands;

use App\LocalConfig;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class UseContext extends BaseCommand
{
    protected $signature = 'use
                            {application? : Application name or ID}
                            {environment? : Environment name or ID}
                            {--clear : Clear saved context}';

    protected $description = 'Set or display the current application and environment context';

    public function handle(LocalConfig $localConfig)
    {
        intro('Context');

        if ($this->option('clear')) {
            $localConfig->remove('application_id', 'environment_id');

            outro('Context cleared.');

            return self::SUCCESS;
        }

        if (! $this->argument('application') && ! $this->argument('environment')) {
            return $this->displayContext($localConfig);
        }

        $this->ensureClient();

        $newValues = [];

        if ($this->argument('application')) {
            $application = $this->resolvers()->application()->from($this->argument('application'));
            $newValues['application_id'] = $application->id;

            info("Application: {$application->name} ({$application->id})");

            if ($this->argument('environment')) {
                $environment = $this->resolvers()->environment()
                    ->withApplication($application)
                    ->from($this->argument('environment'));
                $newValues['environment_id'] = $environment->id;

                info("Environment: {$environment->name} ({$environment->id})");
            }
        }

        $localConfig->setMany($newValues);

        outro('Context saved to '.$localConfig->path());

        return self::SUCCESS;
    }

    protected function displayContext(LocalConfig $localConfig): int
    {
        $applicationId = $localConfig->applicationId();
        $environmentId = $localConfig->environmentId();

        if (! $applicationId && ! $environmentId) {
            warning('No context set. Run `cloud use <application> [environment]` to set context.');

            return self::SUCCESS;
        }

        dataList(array_filter([
            'Application ID' => $applicationId,
            'Environment ID' => $environmentId,
        ]));

        return self::SUCCESS;
    }
}
