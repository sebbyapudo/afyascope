<?php

namespace App;

enum ProcedureRecordStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function displayName(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
        };
    }
}
