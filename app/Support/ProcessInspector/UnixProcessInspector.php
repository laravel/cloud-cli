<?php

namespace App\Support\ProcessInspector;

class UnixProcessInspector implements ProcessInspector
{
    public function getProcessName(int $pid): ?string
    {
        $comm = @shell_exec("ps -o comm= -p {$pid} 2>/dev/null");

        if ($comm === null || $comm === false) {
            return null;
        }

        return trim($comm);
    }

    public function getParentPid(int $pid): ?int
    {
        $ppid = @shell_exec("ps -o ppid= -p {$pid} 2>/dev/null");

        if ($ppid === null || $ppid === false) {
            return null;
        }

        return (int) trim($ppid);
    }
}
