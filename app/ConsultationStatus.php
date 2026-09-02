<?php

namespace App;

enum ConsultationStatus: string
{
    case InProgress = 'in_progress';
    case Finalized = 'finalized';

    public function displayName(): string
    {
        return match ($this) {
            self::InProgress => 'In Progress',
            self::Finalized => 'Finalized',
        };
    }
}
