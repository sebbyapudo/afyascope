<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\DischargeRecovery;
use App\Http\Requests\StoreRecoveryDischargeRequest;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RecoveryDischargeController extends Controller
{
    public function __invoke(
        StoreRecoveryDischargeRequest $request,
        RecoveryEpisode $recoveryEpisode,
        DischargeRecovery $dischargeRecovery,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $discharge = $dischargeRecovery->handle(
            $actor,
            $recoveryEpisode,
            $request->dischargeAttributes(),
        );

        return redirect()
            ->route('nursing.recovery.show', $recoveryEpisode)
            ->with('status', "Discharge {$discharge->discharge_number} was finalized.");
    }
}
