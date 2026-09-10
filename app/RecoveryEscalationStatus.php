<?php

namespace App;

enum RecoveryEscalationStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function displayName(): string
    {
        return match ($this) {
            self::Open => 'Doctor review required',
            self::Resolved => 'Resolved',
        };
    }
}
