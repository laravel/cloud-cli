<?php

namespace App\Enums;

enum Agent: string
{
    case ClaudeCode = 'claude_code';
    case Codex = 'codex';
    case Cursor = 'cursor';
    case OpenCode = 'opencode';
}
