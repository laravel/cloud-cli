<?php

namespace App\Commands;

use App\Dto\Application;

use function Laravel\Prompts\intro;

class ApplicationGet extends BaseCommand
{
    protected ?string $jsonDataClass = Application::class;

    protected $signature = 'application:get {application? : The application ID or name}';

    protected $description = 'Get application details';

    protected $aliases = ['app:get'];

    public function handle()
    {
        $this->ensureClient();

        intro('Application Details');

        $application = $this->resolvers()->application()->from($this->argument('application'));

        $this->outputJsonIfWanted($application);

        dataList([
            'ID' => $application->id,
            'Name' => $application->name,
            'Region' => $application->region,
            'Repository' => 'https://github.com/'.$application->repositoryFullName,
            'Environments' => collect($application->environments)->map(fn ($env) => [$env->name, $env->id])->toArray(),
            'Organization' => [
                [$application->organization->name, $application->organization->id],
            ],
        ]);
    }
}
