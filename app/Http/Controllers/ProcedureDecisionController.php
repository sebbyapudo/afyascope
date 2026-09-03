<?php

namespace App\Http\Controllers;

use App\Actions\Consultations\RecordProcedureDecision;
use App\Http\Requests\StoreProcedureDecisionRequest;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ProcedureDecisionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreProcedureDecisionRequest $request,
        Consultation $consultation,
        RecordProcedureDecision $recordProcedureDecision,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $decision = $recordProcedureDecision->handle(
            $actor,
            $consultation,
            $request->decisionAttributes(),
        );

        return redirect()->route('clinical.consultations.show', $consultation)->with(
            'status',
            "Procedure decision {$decision->decision_number} was recorded.",
        );
    }
}
