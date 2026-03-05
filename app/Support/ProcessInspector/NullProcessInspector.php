<?php

namespace App\Support\ProcessInspector;

class NullProcessInspector implements ProcessInspector
{
    public function getProcessName(int $pid): ?string
    {
        return null;
    }

    public function getParentPid(int $pid): ?int
    {
        return null;
    }
}
