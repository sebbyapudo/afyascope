<?php

namespace App;

enum BillStatus: string
{
    case Open = 'open';

    public function displayName(): string
    {
        return match ($this) {
            self::Open => 'Open',
        };
    }
}
