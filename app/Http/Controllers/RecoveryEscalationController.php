<?php

namespace App\Http\Controllers;

use App\Actions\Clinical\ResolveRecoveryEscalation;
use App\Http\Requests\ResolveRecoveryEscalationRequest;
use App\Models\Patient;
use App\Models\RecoveryEscalation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RecoveryEscalationController extends Controller
{
    public function index(): Response
    {
        $escalations = RecoveryEscalation::query()
            ->where('open_marker', true)
            ->with([
                'escalatedBy:id,name',
                'recoveryEpisode:id,visit_id,procedure_record_id,nurse_user_id,recovery_number,status,started_at',
                'recoveryEpisode.nurse:id,name',
                'recoveryEpisode.procedureRecord:id,service_catalog_item_id,procedure_number',
                'recoveryEpisode.procedureRecord.serviceCatalogItem:id,name',
                'recoveryEpisode.visit:id,patient_id,visit_number',
                'recoveryEpisode.visit.patient:id,patient_number,first_name,middle_name,last_name',
            ])
            ->orderBy('escalated_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('clinical/recovery-escalations/index', [
            'escalations' => $escalations->map(function (RecoveryEscalation $escalation): array {
                $recoveryEpisode = $escalation->recoveryEpisode;
                $patient = $recoveryEpisode->visit->patient;

                return [
                    'id' => $escalation->id,
                    'reason' => $escalation->reason,
                    'escalatedAt' => $escalation->escalated_at->toIso8601String(),
                    'escalatedBy' => ['name' => $escalation->escalatedBy->name],
                    'recovery' => [
                        'id' => $recoveryEpisode->id,
                        'recoveryNumber' => $recoveryEpisode->recovery_number,
                        'nurse' => ['name' => $recoveryEpisode->nurse->name],
                    ],
                    'patient' => [
                        'patientNumber' => $patient->patient_number,
                        'name' => $this->patientName($patient),
                    ],
                    'visit' => ['visitNumber' => $recoveryEpisode->visit->visit_number],
                    'procedure' => [
                        'procedureNumber' => $recoveryEpisode->procedureRecord->procedure_number,
                        'name' => $recoveryEpisode->procedureRecord->serviceCatalogItem->name,
                    ],
                ];
            })->values(),
        ]);
    }

    public function update(
        ResolveRecoveryEscalationRequest $request,
        RecoveryEscalation $recoveryEscalation,
        ResolveRecoveryEscalation $resolveRecoveryEscalation,
    ): RedirectResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $resolveRecoveryEscalation->handle(
            $actor,
            $recoveryEscalation,
            $request->resolutionAttributes(),
        );

        return redirect()
            ->route('nursing.recovery.show', $recoveryEscalation->recovery_episode_id)
            ->with('status', 'Recovery escalation was resolved and returned to Nursing.');
    }

    private function patientName(Patient $patient): string
    {
        return collect([
            $patient->first_name,
            $patient->middle_name,
            $patient->last_name,
        ])->filter()->implode(' ');
    }
}
