<?php

namespace Tests;

use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The CLI decides between interactive and headless behaviour from the environment
     * (TTY, CI and agent env vars). The suite expects headless, which CI and agents
     * provide but a developer's own terminal doesn't, so these pin it to match CI.
     *
     * NO_COLOR matters because a decorated (TTY) output makes Laravel Prompts' spinner
     * fork a child process, which can outlive the test and signal the test runner.
     */
    protected const ENVIRONMENT = [
        'CI' => 'true',
        'NO_COLOR' => '1',
    ];

    /** @var array<string, string|false> */
    protected array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (static::ENVIRONMENT as $key => $value) {
            $this->originalEnvironment[$key] = getenv($key);
            putenv("{$key}={$value}");
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            putenv($value === false ? $key : "{$key}={$value}");
        }
    }
}
