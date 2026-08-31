<?php

namespace App;

enum VisitStatus: string
{
    case Created = 'created';

    public function displayName(): string
    {
        return match ($this) {
            self::Created => 'Created',
        };
    }

    public function handoffLabel(): string
    {
        return match ($this) {
            self::Created => 'Awaiting consultation billing',
        };
    }
}
