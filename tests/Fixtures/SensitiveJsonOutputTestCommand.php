<?php

namespace Tests\Fixtures;

use App\Commands\BaseCommand;
use App\Dto\Environment;

class SensitiveJsonOutputTestCommand extends BaseCommand
{
    protected $signature = 'test:sensitive-json';

    public function handle(): void
    {
        $env = Environment::from([
            'id' => 'env-1',
            'url' => 'https://example.com',
            'name' => 'production',
            'branch' => 'main',
            'status' => 'running',
            'instances' => null,
            'buildCommand' => null,
            'deployCommand' => null,
            'slug' => 'production',
            'statusEnum' => 'running',
            'createdFromAutomation' => false,
            'vanityDomain' => 'example.com',
            'phpMajorVersion' => '8.3',
            'environmentVariables' => [
                ['key' => 'APP_KEY', 'value' => 'base64:secret'],
                ['key' => 'STRIPE_SECRET', 'value' => 'sk_live_xyz'],
            ],
        ]);

        $this->outputJsonIfWanted($env);
    }
}
