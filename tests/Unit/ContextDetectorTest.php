<?php

use App\Enums\Agent;
use App\Support\ContextDetector;

beforeEach(function () {
    ContextDetector::flush();
});

afterEach(function () {
    putenv('CLAUDECODE');
    putenv('CODEX_THREAD_ID');
    putenv('CURSOR_AGENT');
    putenv('OPENCODE');
    putenv('TERM_PROGRAM');
    ContextDetector::flush();
});

it('detects agent from env vars', function (string $envVar, string $value, Agent $expected) {
    putenv("{$envVar}={$value}");

    expect(ContextDetector::agent())->toBe($expected);
})->with([
    'Claude Code' => ['CLAUDECODE', '1', Agent::ClaudeCode],
    'Codex' => ['CODEX_THREAD_ID', 'thread-123', Agent::Codex],
    'Cursor' => ['CURSOR_AGENT', '1', Agent::Cursor],
    'OpenCode' => ['OPENCODE', '1', Agent::OpenCode],
]);

it('returns null agent from env when no env vars are set', function () {
    $method = new ReflectionMethod(ContextDetector::class, 'agentFromEnv');

    expect($method->invoke(null))->toBeNull();
});

it('detects terminal from TERM_PROGRAM', function (string $termProgram, string $expected) {
    putenv("TERM_PROGRAM={$termProgram}");

    expect(ContextDetector::terminal())->toBe($expected);
})->with([
    'Ghostty' => ['ghostty', 'Ghostty'],
    'iTerm' => ['iTerm.app', 'iTerm'],
    'Terminal' => ['Apple_Terminal', 'Terminal'],
    'VS Code' => ['vscode', 'VS Code'],
    'WezTerm' => ['WezTerm', 'WezTerm'],
    'Alacritty' => ['Alacritty', 'Alacritty'],
    'tmux' => ['tmux', 'tmux'],
]);

it('passes through unknown terminal names', function () {
    putenv('TERM_PROGRAM=kitty');

    expect(ContextDetector::terminal())->toBe('kitty');
});

it('returns null terminal when TERM_PROGRAM is unset', function () {
    putenv('TERM_PROGRAM');

    expect(ContextDetector::terminal())->toBeNull();
});

it('caches agent detection result', function () {
    putenv('CLAUDECODE=1');

    ContextDetector::agent();
    putenv('CLAUDECODE');
    putenv('CODEX_THREAD_ID=thread-123');

    expect(ContextDetector::agent())->toBe(Agent::ClaudeCode);
});

it('caches terminal detection result', function () {
    putenv('TERM_PROGRAM=ghostty');

    ContextDetector::terminal();
    putenv('TERM_PROGRAM=iTerm.app');

    expect(ContextDetector::terminal())->toBe('Ghostty');
});
