<?php

namespace App\Support;

use AgentDetector\AgentDetector;

class ContextDetector
{
    protected static ?string $resolvedAgent = null;

    protected static bool $agentResolved = false;

    protected static ?string $resolvedTerminal = null;

    protected static bool $terminalResolved = false;

    protected static array $terminals = [
        'ghostty' => 'Ghostty',
        'iTerm.app' => 'iTerm',
        'Apple_Terminal' => 'Terminal',
        'vscode' => 'VS Code',
        'WezTerm' => 'WezTerm',
        'Alacritty' => 'Alacritty',
        'tmux' => 'tmux',
    ];

    public static function agent(): ?string
    {
        if (! static::$agentResolved) {
            $result = AgentDetector::detect();

            static::$resolvedAgent = $result->isAgent
                ? ($result->knownAgent()->value ?? $result->name)
                : null;

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
}
