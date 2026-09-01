<?php

namespace App;

enum BillStatus: string
{
    case Open = 'open';
    case Paid = 'paid';

    public function displayName(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Paid => 'Paid',
        };
    }
}
