<?php

namespace App;

enum RecoveryEpisodeStatus: string
{
    case InProgress = 'in_progress';
    case ReadyForDischarge = 'ready_for_discharge';
    case Completed = 'completed';

    public function displayName(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::ReadyForDischarge => 'Ready for discharge',
            self::Completed => 'Completed',
        };
    }
}
