<?php

namespace App;

enum RecoveryEscalationResolution: string
{
    case ContinueMonitoring = 'continue_monitoring';
    case ClinicallyCleared = 'clinically_cleared';

    public function displayName(): string
    {
        return match ($this) {
            self::ContinueMonitoring => 'Continue Nursing monitoring',
            self::ClinicallyCleared => 'Clinically cleared for Nurse readiness assessment',
        };
    }
}
