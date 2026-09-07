<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\CompletePreProcedureReadiness;
use App\Http\Requests\CompletePreProcedureReadinessRequest;
use App\Models\PreProcedureReadiness;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CompletePreProcedureReadinessController extends Controller
{
    public function __invoke(
        CompletePreProcedureReadinessRequest $request,
        PreProcedureReadiness $preProcedureReadiness,
        CompletePreProcedureReadiness $completePreProcedureReadiness,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $completePreProcedureReadiness->handle($actor, $preProcedureReadiness);

        return redirect()->route('nursing.pre-procedure-readiness.show', $preProcedureReadiness)->with(
            'status',
            "Readiness {$preProcedureReadiness->readiness_number} was completed. The patient is ready for the Doctor procedure.",
        );
    }
}
