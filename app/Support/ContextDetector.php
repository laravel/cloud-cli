<?php

namespace App\Support;

use App\Enums\Agent;

class ContextDetector
{
    protected static ?Agent $resolvedAgent = null;

    protected static bool $agentResolved = false;

    protected static ?string $resolvedTerminal = null;

    protected static bool $terminalResolved = false;

    protected static array $envVars = [
        'CLAUDECODE' => Agent::ClaudeCode,
        'CODEX_THREAD_ID' => Agent::Codex,
        'CURSOR_AGENT' => Agent::Cursor,
        'OPENCODE' => Agent::OpenCode,
    ];

    protected static array $terminals = [
        'ghostty' => 'Ghostty',
        'iTerm.app' => 'iTerm',
        'Apple_Terminal' => 'Terminal',
        'vscode' => 'VS Code',
        'WezTerm' => 'WezTerm',
        'Alacritty' => 'Alacritty',
        'tmux' => 'tmux',
    ];

    public static function agent(): ?Agent
    {
        if (! static::$agentResolved) {
            static::$resolvedAgent = static::agentFromEnv();
            static::$agentResolved = true;
        }

        return static::$resolvedAgent;
    }

    public static function terminal(): ?string
    {
        if (! static::$terminalResolved) {
            $termProgram = getenv('TERM_PROGRAM');

            static::$resolvedTerminal = ($termProgram === false || $termProgram === '')
                ? null
                : (static::$terminals[$termProgram] ?? $termProgram);

            static::$terminalResolved = true;
        }

        return static::$resolvedTerminal;
    }

    public static function flush(): void
    {
        static::$agentResolved = false;
        static::$resolvedAgent = null;
        static::$terminalResolved = false;
        static::$resolvedTerminal = null;
    }

    protected static function agentFromEnv(): ?Agent
    {
        foreach (static::$envVars as $envVar => $agent) {
            if (! empty(getenv($envVar))) {
                return $agent;
            }
        }

        return null;
    }
}
