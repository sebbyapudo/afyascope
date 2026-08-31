<?php

namespace App;

enum BillType: string
{
    case Consultation = 'consultation';
    case Procedure = 'procedure';

    public function displayName(): string
    {
        return match ($this) {
            self::Consultation => 'Consultation',
            self::Procedure => 'Procedure',
        };
    }
}
