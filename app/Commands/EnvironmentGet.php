<?php

namespace App\Commands;

use function Laravel\Prompts\intro;

class EnvironmentGet extends BaseCommand
{
    protected $aliases = ['status'];

    protected $signature = 'environment:get {environment? : The environment ID or name} {--json : Output as JSON}';

    protected $description = 'Get environment details';

    public function handle()
    {
        $this->ensureClient();

        intro('Environment Details');

        $environment = $this->resolvers()->environment()->include('application')->from($this->argument('environment'));
        $application = $this->client->applications()->get($environment->application->id);

        $this->outputJsonIfWanted($environment);

        dataList([
            'ID' => $environment->id,
            'Name' => $environment->name,
            'Branch' => $environment->branch ?? 'N/A',
            'Status' => $environment->status,
            'Web URL' => $environment->url,
            'Dashboard URL' => $application->url($environment),
            'PHP Version' => $environment->phpMajorVersion,
            'Instances' => count($environment->instances ?? []),
        ]);
    }
}
