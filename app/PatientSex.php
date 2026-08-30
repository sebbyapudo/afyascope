<?php

namespace App;

enum PatientSex: string
{
    case Female = 'female';
    case Male = 'male';
    case Other = 'other';

    public function displayName(): string
    {
        return match ($this) {
            self::Female => 'Female',
            self::Male => 'Male',
            self::Other => 'Other',
        };
    }
}
