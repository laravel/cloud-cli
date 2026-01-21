<?php

namespace App\Commands;

use App\Concerns\HasAClient;
use App\Concerns\RequiresApplication;

class ApplicationGet extends BaseCommand
{
    use HasAClient;
    use RequiresApplication;

    protected $signature = 'application:get {application? : The application ID or name} {--json : Output as JSON}';

    protected $description = 'Get application details';

    public function handle()
    {
        $this->ensureClient();

        $this->intro('Application Details', $this->argument('application'));

        $application = $this->getCloudApplication(showPrompt: false);

        if ($this->wantsJson()) {
            $this->line($application->toJson());

            return;
        }

        dataList([
            'Name' => $application->name,
            'ID' => $application->id,
            'Region' => $application->region,
            'Repository' => 'https://github.com/'.$application->repositoryFullName,
            'Environments' => collect($application->environments)->map(fn ($env) => $env->name.' '.$this->dim($env->id).'')->toArray(),
            'Organization' => $application->organization->name.' '.$this->dim($application->organization->id).'',
        ]);
    }
}
