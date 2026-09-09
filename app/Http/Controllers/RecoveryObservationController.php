<?php

namespace App\Http\Controllers;

use App\Actions\Nursing\RecordRecoveryObservation;
use App\Http\Requests\StoreRecoveryObservationRequest;
use App\Models\RecoveryEpisode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class RecoveryObservationController extends Controller
{
    public function store(StoreRecoveryObservationRequest $request, RecoveryEpisode $recoveryEpisode, RecordRecoveryObservation $action): RedirectResponse
    {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $action->handle($actor, $recoveryEpisode, $request->observationAttributes());

        return redirect()->route('nursing.recovery.show', $recoveryEpisode)
            ->with('status', 'Recovery observation was recorded.');
    }
}
