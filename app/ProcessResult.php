<?php

namespace App;

class ProcessResult
{
    public function __construct(
        protected bool $success,
        protected string $output,
        protected string $errorOutput,
    ) {
        //
    }

    public function successful(): bool
    {
        return $this->success;
    }

    public function output(): string
    {
        return $this->output;
    }

    public function errorOutput(): string
    {
        return $this->errorOutput;
    }
}
