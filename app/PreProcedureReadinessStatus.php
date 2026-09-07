<?php

namespace App;

enum PreProcedureReadinessStatus: string
{
    case InPreparation = 'in_preparation';
    case Ready = 'ready';

    public function displayName(): string
    {
        return match ($this) {
            self::InPreparation => 'In preparation',
            self::Ready => 'Ready',
        };
    }
}
