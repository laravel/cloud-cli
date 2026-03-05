<?php

namespace App\Support\ProcessInspector;

interface ProcessInspector
{
    public function getProcessName(int $pid): ?string;

    public function getParentPid(int $pid): ?int;
}
