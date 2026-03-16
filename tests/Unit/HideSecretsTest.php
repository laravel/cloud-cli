<?php

use App\Commands\BaseCommand;

it('redacts environment variable values when hide-secrets flag is used', function () {
    $command = new class extends BaseCommand
    {
        protected $signature = 'test:hide-secrets {--hide-secrets}';

        public function handle()
        {
            //
        }

        public function testRedact(mixed $data): mixed
        {
            return $this->redactSecrets($data);
        }
    };

    // Simple key/value pair
    $result = $command->testRedact(['key' => 'DB_PASSWORD', 'value' => 'super-secret']);
    expect($result)->toBe(['key' => 'DB_PASSWORD', 'value' => '********']);

    // Nested inside an array of env vars
    $result = $command->testRedact([
        'environmentVariables' => [
            ['key' => 'APP_KEY', 'value' => 'base64:abc123'],
            ['key' => 'DB_HOST', 'value' => '127.0.0.1'],
        ],
    ]);
    expect($result['environmentVariables'][0]['value'])->toBe('********');
    expect($result['environmentVariables'][1]['value'])->toBe('********');
    expect($result['environmentVariables'][0]['key'])->toBe('APP_KEY');
    expect($result['environmentVariables'][1]['key'])->toBe('DB_HOST');

    // Non-env-var data is not affected
    $result = $command->testRedact(['name' => 'my-app', 'status' => 'running']);
    expect($result)->toBe(['name' => 'my-app', 'status' => 'running']);

    // Deeply nested structure
    $result = $command->testRedact([
        'data' => [
            'attributes' => [
                'environment_variables' => [
                    ['key' => 'SECRET', 'value' => 'hidden'],
                ],
            ],
        ],
    ]);
    expect($result['data']['attributes']['environment_variables'][0]['value'])->toBe('********');
    expect($result['data']['attributes']['environment_variables'][0]['key'])->toBe('SECRET');
});

it('does not redact when value key is not a simple key/value env var pair', function () {
    $command = new class extends BaseCommand
    {
        protected $signature = 'test:hide-secrets-2 {--hide-secrets}';

        public function handle()
        {
            //
        }

        public function testRedact(mixed $data): mixed
        {
            return $this->redactSecrets($data);
        }
    };

    // Array with key but no value should not be modified
    $result = $command->testRedact(['key' => 'something']);
    expect($result)->toBe(['key' => 'something']);

    // Non-string key should not be redacted
    $result = $command->testRedact(['key' => 123, 'value' => 'test']);
    expect($result)->toBe(['key' => 123, 'value' => 'test']);
});
