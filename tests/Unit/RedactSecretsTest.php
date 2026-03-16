<?php

use App\Commands\BaseCommand;

/**
 * Create a testable instance of BaseCommand that exposes the redactSecrets method.
 */
function createTestCommand(): object
{
    return new class extends BaseCommand
    {
        protected $signature = 'test:redact {--hide-secrets}';

        protected $description = 'Test command';

        public function handle()
        {
            //
        }

        public function testRedactSecrets(mixed $data): mixed
        {
            return $this->redactSecrets($data);
        }

        public function testIsSensitiveFieldName(string $name): bool
        {
            return $this->isSensitiveFieldName($name);
        }

        public function testIsSensitiveKeyName(string $key): bool
        {
            return $this->isSensitiveKeyName($key);
        }
    };
}

// --- isSensitiveFieldName ---

it('identifies password as a sensitive field name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveFieldName('password'))->toBeTrue();
});

it('identifies secret as a sensitive field name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveFieldName('secret'))->toBeTrue();
});

it('identifies secret_access_key as a sensitive field name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveFieldName('secret_access_key'))->toBeTrue();
});

it('identifies secretAccessKey as a sensitive field name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveFieldName('secretAccessKey'))->toBeTrue();
});

it('does not flag hostname as a sensitive field name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveFieldName('hostname'))->toBeFalse();
});

it('does not flag name as a sensitive field name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveFieldName('name'))->toBeFalse();
});

// --- isSensitiveKeyName ---

it('identifies DB_PASSWORD as a sensitive key name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveKeyName('DB_PASSWORD'))->toBeTrue();
});

it('identifies APP_SECRET as a sensitive key name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveKeyName('APP_SECRET'))->toBeTrue();
});

it('identifies AWS_ACCESS_KEY as a sensitive key name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveKeyName('AWS_ACCESS_KEY'))->toBeTrue();
});

it('does not flag APP_NAME as a sensitive key name', function () {
    $command = createTestCommand();
    expect($command->testIsSensitiveKeyName('APP_NAME'))->toBeFalse();
});

// --- redactSecrets ---

it('redacts password fields in connection arrays', function () {
    $command = createTestCommand();

    $data = [
        'connection' => [
            'hostname' => 'redis.example.com',
            'port' => 6379,
            'password' => 'super-secret-password',
        ],
    ];

    $result = $command->testRedactSecrets($data);

    expect($result['connection']['hostname'])->toBe('redis.example.com');
    expect($result['connection']['port'])->toBe(6379);
    expect($result['connection']['password'])->toBe('********');
});

it('redacts secret_access_key fields in nested objects', function () {
    $command = createTestCommand();

    $data = [
        'id' => 'key-1',
        'name' => 'my-key',
        'accessKeyId' => 'AKIA12345',
        'secretAccessKey' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    ];

    $result = $command->testRedactSecrets($data);

    expect($result['id'])->toBe('key-1');
    expect($result['name'])->toBe('my-key');
    expect($result['accessKeyId'])->toBe('AKIA12345');
    expect($result['secretAccessKey'])->toBe('********');
});

it('redacts environment variable style arrays when key is sensitive', function () {
    $command = createTestCommand();

    $data = [
        ['key' => 'APP_NAME', 'value' => 'My App'],
        ['key' => 'DB_PASSWORD', 'value' => 'secret123'],
        ['key' => 'API_TOKEN', 'value' => 'tok_abc'],
    ];

    $result = $command->testRedactSecrets($data);

    expect($result[0]['value'])->toBe('My App');
    expect($result[1]['value'])->toBe('********');
    expect($result[2]['value'])->toBe('********');
});

it('handles deeply nested structures', function () {
    $command = createTestCommand();

    $data = [
        'caches' => [
            [
                'id' => 'cache-1',
                'name' => 'redis',
                'connection' => [
                    'hostname' => 'redis.example.com',
                    'password' => 'my-redis-password',
                ],
            ],
            [
                'id' => 'cache-2',
                'name' => 'memcached',
                'connection' => [
                    'hostname' => 'memcached.example.com',
                    'password' => 'my-memcached-password',
                ],
            ],
        ],
    ];

    $result = $command->testRedactSecrets($data);

    expect($result['caches'][0]['connection']['hostname'])->toBe('redis.example.com');
    expect($result['caches'][0]['connection']['password'])->toBe('********');
    expect($result['caches'][1]['connection']['password'])->toBe('********');
});

it('leaves non-sensitive fields untouched', function () {
    $command = createTestCommand();

    $data = [
        'id' => 'cache-1',
        'name' => 'redis',
        'type' => 'redis',
        'status' => 'running',
        'region' => 'us-east-1',
    ];

    $result = $command->testRedactSecrets($data);

    expect($result)->toBe($data);
});

it('handles empty arrays', function () {
    $command = createTestCommand();
    expect($command->testRedactSecrets([]))->toBe([]);
});

it('handles non-array data', function () {
    $command = createTestCommand();
    expect($command->testRedactSecrets('hello'))->toBe('hello');
    expect($command->testRedactSecrets(42))->toBe(42);
    expect($command->testRedactSecrets(null))->toBeNull();
});

it('redacts database cluster connection passwords', function () {
    $command = createTestCommand();

    $data = [
        'id' => 'cluster-1',
        'name' => 'my-db',
        'type' => 'mysql',
        'connection' => [
            'hostname' => 'db.example.com',
            'port' => 3306,
            'username' => 'admin',
            'password' => 'db-password-123',
        ],
    ];

    $result = $command->testRedactSecrets($data);

    expect($result['connection']['hostname'])->toBe('db.example.com');
    expect($result['connection']['username'])->toBe('admin');
    expect($result['connection']['password'])->toBe('********');
});

it('redacts token fields', function () {
    $command = createTestCommand();

    $data = [
        'name' => 'webhook',
        'token' => 'whsec_12345abcde',
    ];

    $result = $command->testRedactSecrets($data);

    expect($result['name'])->toBe('webhook');
    expect($result['token'])->toBe('********');
});

it('redacts api_key fields', function () {
    $command = createTestCommand();

    $data = [
        'service' => 'stripe',
        'api_key' => 'sk_live_12345',
    ];

    $result = $command->testRedactSecrets($data);

    expect($result['service'])->toBe('stripe');
    expect($result['api_key'])->toBe('********');
});
