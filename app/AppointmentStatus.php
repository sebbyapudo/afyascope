<?php

namespace App;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function displayName(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
        };
    }
}
