<?php

use App\Commands\BaseCommand;
use App\Dto\Environment;
use App\Support\SensitiveValues;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::registerCommand(new SensitiveJsonOutputTestCommand);
});

afterEach(function () {
    SensitiveValues::$reveal = false;
});

it('masks environmentVariables in JSON output by default', function () {
    $exitCode = Artisan::call('test:sensitive-json', ['--no-interaction' => true]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['environmentVariables'])->toEqual([
        ['key' => 'APP_KEY', 'value' => '*****'],
        ['key' => 'STRIPE_SECRET', 'value' => '*****'],
    ]);
});

it('reveals environmentVariables when --show-sensitive is passed', function () {
    $exitCode = Artisan::call('test:sensitive-json', [
        '--no-interaction' => true,
        '--show-sensitive' => true,
    ]);

    expect($exitCode)->toBe(0);

    $payload = json_decode(Artisan::output(), true);

    expect($payload['environmentVariables'])->toEqual([
        ['key' => 'APP_KEY', 'value' => 'base64:secret'],
        ['key' => 'STRIPE_SECRET', 'value' => 'sk_live_xyz'],
    ]);
});

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
