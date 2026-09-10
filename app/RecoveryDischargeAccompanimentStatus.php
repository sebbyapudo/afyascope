<?php

namespace App;

enum RecoveryDischargeAccompanimentStatus: string
{
    case Accompanied = 'accompanied';
    case NotAccompanied = 'not_accompanied';
    case NotApplicable = 'not_applicable';

    public function displayName(): string
    {
        return match ($this) {
            self::Accompanied => 'Accompanied',
            self::NotAccompanied => 'Not accompanied',
            self::NotApplicable => 'Not applicable',
        };
    }
}
