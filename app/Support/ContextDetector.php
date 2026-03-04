<?php

namespace App\Support;

use App\Enums\Agent;

class ContextDetector
{
    protected static array $envVars = [
        'CLAUDECODE' => Agent::ClaudeCode,
        'CODEX_THREAD_ID' => Agent::Codex,
        'CURSOR_AGENT' => Agent::Cursor,
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
        return static::agentFromEnv() ?? static::agentFromProcessTree();
    }

    public static function terminal(): ?string
    {
        $termProgram = getenv('TERM_PROGRAM');

        if ($termProgram === false || $termProgram === '') {
            return null;
        }

        return static::$terminals[$termProgram] ?? $termProgram;
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

        while ($pid > 1) {
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
