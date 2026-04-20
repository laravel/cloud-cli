<?php

use App\ConfigRepository;
use App\Middleware\OffersSkillsInstall;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

beforeEach(function () {
    $this->mockConfig = Mockery::mock(ConfigRepository::class);
    $this->app->instance(ConfigRepository::class, $this->mockConfig);
});

function runMiddleware(OffersSkillsInstall $middleware, string $command = 'application:list'): bool
{
    $called = false;

    $middleware->handle($command, function () use (&$called) {
        $called = true;
    });

    return $called;
}

function partialMiddleware(array $overrides = []): OffersSkillsInstall
{
    $defaults = [
        'isInteractiveSession' => true,
        'isGloballyInstalled' => true,
        'detectAgents' => ['claude'],
        'skillsAlreadyInstalled' => false,
    ];

    $middleware = Mockery::mock(OffersSkillsInstall::class)->makePartial();
    $middleware->shouldAllowMockingProtectedMethods();

    foreach (array_merge($defaults, $overrides) as $method => $value) {
        $middleware->shouldReceive($method)->andReturn($value);
    }

    return $middleware;
}

it('skips when the command is in the skip list', function () {
    $this->mockConfig->shouldNotReceive('get');

    expect(runMiddleware(new OffersSkillsInstall, 'auth'))->toBeTrue();
});

it('skips when the session is non-interactive', function () {
    // Under phpunit, STDIN is not a TTY so isInteractiveSession() returns false.
    $this->mockConfig->shouldNotReceive('get');

    expect(runMiddleware(new OffersSkillsInstall))->toBeTrue();
});

it('skips when the CLI is a project dependency', function () {
    $this->mockConfig->shouldNotReceive('get');

    $middleware = partialMiddleware(['isGloballyInstalled' => false]);

    expect(runMiddleware($middleware))->toBeTrue();
});

it('skips when no agents are detected', function () {
    $this->mockConfig->shouldNotReceive('get');

    $middleware = partialMiddleware(['detectAgents' => []]);

    expect(runMiddleware($middleware))->toBeTrue();
});

it('skips when skills are already installed for a detected agent', function () {
    $this->mockConfig->shouldNotReceive('get');

    $middleware = partialMiddleware(['skillsAlreadyInstalled' => true]);

    expect(runMiddleware($middleware))->toBeTrue();
});

it('skips when the prompted flag is already set', function () {
    $this->mockConfig->shouldReceive('get')->with('skills_install_prompted_at')->andReturn('2026-04-20T12:00:00+00:00');
    $this->mockConfig->shouldNotReceive('set');

    $middleware = partialMiddleware();

    expect(runMiddleware($middleware))->toBeTrue();
});

it('prompts, installs skills, and records the choice when the user confirms', function () {
    Prompt::fake([Key::ENTER]);

    $this->mockConfig->shouldReceive('get')->with('skills_install_prompted_at')->andReturn(null);
    $this->mockConfig->shouldReceive('set')
        ->with('skills_install_prompted_at', Mockery::type('string'))
        ->once();

    $middleware = partialMiddleware();
    $middleware->shouldReceive('runInstall')->once();
    $middleware->shouldReceive('showDeclineHint')->never();

    expect(runMiddleware($middleware))->toBeTrue();
});

it('records the choice without installing when the user declines', function () {
    Prompt::fake([Key::TAB, Key::ENTER]);

    $this->mockConfig->shouldReceive('get')->with('skills_install_prompted_at')->andReturn(null);
    $this->mockConfig->shouldReceive('set')
        ->with('skills_install_prompted_at', Mockery::type('string'))
        ->once();

    $middleware = partialMiddleware();
    $middleware->shouldReceive('runInstall')->never();
    $middleware->shouldReceive('showDeclineHint')->once();

    expect(runMiddleware($middleware))->toBeTrue();
});
