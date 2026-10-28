<?php

use App\Concerns\ReadsLocalEnvironmentVariables;

beforeEach(function () {
    $this->reader = new class
    {
        use ReadsLocalEnvironmentVariables {
            localEnvironmentVariables as public;
        }
    };

    $this->directory = sys_get_temp_dir().'/reads-local-env-'.uniqid();
    mkdir($this->directory);
});

afterEach(function () {
    @unlink($this->directory.'/.env');
    rmdir($this->directory);
});

it('leaves out the keys every Laravel app has', function () {
    file_put_contents($this->directory.'/.env', "APP_KEY=secret\nDB_CONNECTION=sqlite\nSLACK_BOT_TOKEN=\"xoxb-1\"\n");

    expect($this->reader->localEnvironmentVariables($this->directory))->toBe(['SLACK_BOT_TOKEN' => 'xoxb-1']);
    expect($this->reader->localEnvironmentVariables($this->directory, includeLaravelKeys: true))->toBe([
        'APP_KEY' => 'secret',
        'DB_CONNECTION' => 'sqlite',
        'SLACK_BOT_TOKEN' => 'xoxb-1',
    ]);
});

it('returns nothing when there is no .env file', function () {
    expect($this->reader->localEnvironmentVariables($this->directory))->toBe([]);
});
