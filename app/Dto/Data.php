<?php

namespace App\Dto;

abstract class Data
{
    abstract public function toArray(): array;

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
