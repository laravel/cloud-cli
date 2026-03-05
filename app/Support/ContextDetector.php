<?php

namespace App\Support;

use App\Enums\Agent;
use App\Support\ProcessInspector\NullProcessInspector;
use App\Support\ProcessInspector\ProcessInspector;
use App\Support\ProcessInspector\UnixProcessInspector;

class ContextDetector
{
    protected static ?Agent $resolvedAgent = null;

    protected static bool $agentResolved = false;

    protected static ?string $resolvedTerminal = null;

    protected static bool $terminalResolved = false;

    protected static ?ProcessInspector $processInspector = null;

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
        'opencode' => Agent::OpenCode,
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

    public static function setProcessInspector(ProcessInspector $inspector): void
    {
        static::$processInspector = $inspector;
    }

    public static function flush(): void
    {
        static::$agentResolved = false;
        static::$resolvedAgent = null;
        static::$terminalResolved = false;
        static::$resolvedTerminal = null;
        static::$processInspector = null;
    }

    protected static function processInspector(): ProcessInspector
    {
        return static::$processInspector ??= PHP_OS_FAMILY === 'Windows'
            ? new NullProcessInspector
            : new UnixProcessInspector;
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
        $inspector = static::processInspector();
        $pid = (int) getmypid();

        $depth = 0;

        while ($pid > 1 && $depth++ < 15) {
            $comm = $inspector->getProcessName($pid);

            if ($comm === null) {
                break;
            }

            foreach (static::$processes as $needle => $agent) {
                if (str_contains($comm, $needle)) {
                    return $agent;
                }
            }

            $ppid = $inspector->getParentPid($pid);

            if ($ppid === null) {
                break;
            }

            $pid = $ppid;
        }

        return null;
    }
}
