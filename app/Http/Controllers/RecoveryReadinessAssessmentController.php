<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\AssessRecoveryReadiness;
use App\Http\Requests\AssessRecoveryReadinessRequest;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RecoveryReadinessAssessmentController extends Controller
{
    public function __invoke(
        AssessRecoveryReadinessRequest $request,
        RecoveryEpisode $recoveryEpisode,
        AssessRecoveryReadiness $assessRecoveryReadiness,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $assessment = $assessRecoveryReadiness->handle(
            $actor,
            $recoveryEpisode,
            $request->assessmentAttributes(),
        );

        $message = $assessment->clinical_concern_requires_escalation
            ? 'Recovery was escalated for Doctor review.'
            : ($assessment->criteria_met
                ? 'Recovery is ready for discharge.'
                : 'Recovery readiness assessment was recorded.');

        return redirect()
            ->route('nursing.recovery.show', $recoveryEpisode)
            ->with('status', $message);
    }
}
