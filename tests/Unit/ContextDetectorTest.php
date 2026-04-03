<?php

use App\Support\ContextDetector;

beforeEach(function () {
    ContextDetector::flush();
});

afterEach(function () {
    putenv('CLAUDECODE');
    putenv('CLAUDE_CODE');
    putenv('CODEX_SANDBOX');
    putenv('CURSOR_AGENT');
    putenv('CURSOR_TRACE_ID');
    putenv('OPENCODE');
    putenv('OPENCODE_CLIENT');
    putenv('TERM_PROGRAM');
    ContextDetector::flush();
});

it('detects agent from env vars', function (string $envVar, string $value, string $expected) {
    putenv("{$envVar}={$value}");

    expect(ContextDetector::agent())->toBe($expected);
})->with([
    'Claude Code' => ['CLAUDECODE', '1', 'claude'],
    'Codex' => ['CODEX_SANDBOX', '1', 'codex'],
    'Cursor CLI' => ['CURSOR_AGENT', '1', 'cursor'],
    'OpenCode' => ['OPENCODE', '1', 'opencode'],
]);

it('returns null agent when no env vars are set', function () {
    expect(ContextDetector::agent())->toBeNull();
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
    putenv('CODEX_SANDBOX=1');

    expect(ContextDetector::agent())->toBe('claude');
});

it('caches terminal detection result', function () {
    putenv('TERM_PROGRAM=ghostty');

    ContextDetector::terminal();
    putenv('TERM_PROGRAM=iTerm.app');

    expect(ContextDetector::terminal())->toBe('Ghostty');
});
