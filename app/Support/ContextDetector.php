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

    protected static array $processes = [
        'claude' => Agent::ClaudeCode,
        'codex' => Agent::Codex,
        'cursor-agent' => Agent::Cursor,
        'Cursor Helper' => Agent::Cursor,
        'Cursor.app' => Agent::Cursor,
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
            static::$resolvedAgent = static::agentFromEnv() ?? static::agentFromProcessTree();
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

    protected static function agentFromProcessTree(): ?Agent
    {
        $pid = getmypid();

        $depth = 0;

        while ($pid > 1 && $depth++ < 15) {
            $comm = @shell_exec("ps -o comm= -p {$pid} 2>/dev/null");

            if ($comm === null || $comm === false) {
                break;
            }

            $comm = trim($comm);

            foreach (static::$processes as $needle => $agent) {
                if (str_contains($comm, $needle)) {
                    return $agent;
                }
            }

            $ppid = @shell_exec("ps -o ppid= -p {$pid} 2>/dev/null");

            if ($ppid === null || $ppid === false) {
                break;
            }

            $pid = (int) trim($ppid);
        }

        return null;
    }
}
