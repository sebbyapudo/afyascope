<?php

namespace App;

enum ProcedureDecisionOutcome: string
{
    case ProcedureRequired = 'procedure_required';
    case NoProcedure = 'no_procedure';

    public function displayName(): string
    {
        return match ($this) {
            self::ProcedureRequired => 'Procedure required',
            self::NoProcedure => 'No procedure required',
        };
    }
}
