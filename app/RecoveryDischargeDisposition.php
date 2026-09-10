<?php

namespace App;

enum RecoveryDischargeDisposition: string
{
    case Home = 'home';
    case OtherFacility = 'other_facility';
    case Other = 'other';

    public function displayName(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::OtherFacility => 'Other care facility',
            self::Other => 'Other destination',
        };
    }
}
