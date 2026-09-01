<?php

namespace App;

enum VisitStatus: string
{
    case Created = 'created';
    case CheckedIn = 'checked_in';

    public function displayName(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::CheckedIn => 'Checked In',
        };
    }

    public function handoffLabel(): string
    {
        return match ($this) {
            self::Created => 'Awaiting consultation billing',
            self::CheckedIn => 'Ready for Doctor consultation',
        };
    }
}
