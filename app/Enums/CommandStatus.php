<?php

namespace App\Enums;

enum CommandStatus: string
{
    case PENDING = 'pending';
    case CREATED = 'command.created';
    case RUNNING = 'command.running';
    case FAILURE = 'command.failure';
    case SUCCESS = 'command.success';

    public function label(): string
    {
        return str($this->name)->lower()->replace('_', ' ')->ucfirst()->toString();
    }
}
