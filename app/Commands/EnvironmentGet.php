<?php

namespace App\Commands;

use App\Dto\Environment;

use function Laravel\Prompts\intro;

class EnvironmentGet extends BaseCommand
{
    protected ?string $jsonDataClass = Environment::class;

    protected $signature = 'environment:get {environment? : The environment ID or name}';

    protected $description = 'Get environment details';

    protected $aliases = ['env:get'];

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
