<?php

namespace App\Http\Controllers;

use App\Actions\Procedures\CompleteProcedureRecord;
use App\Http\Requests\CompleteProcedureRecordRequest;
use App\Models\ProcedureRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CompleteProcedureRecordController extends Controller
{
    public function __invoke(
        CompleteProcedureRecordRequest $request,
        ProcedureRecord $procedureRecord,
        CompleteProcedureRecord $completeProcedureRecord,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $completeProcedureRecord->handle(
            $actor,
            $procedureRecord,
            $request->expectedLockVersion(),
        );

        return redirect()->route('clinical.procedures.show', $procedureRecord)->with(
            'status',
            "Procedure {$procedureRecord->procedure_number} was completed. The patient is ready for Nursing recovery.",
        );
    }
}
