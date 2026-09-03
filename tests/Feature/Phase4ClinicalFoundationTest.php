<?php

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\ProcedureBillingHandoff;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\VisitStatus;
use Illuminate\Support\Facades\Route;

it('derives Doctor readiness from checked-in Visits without creating clinical or procedure records', function () {
    $readyVisit = VisitCheckIn::factory()->create()->visit->fresh(['checkIn', 'consultation']);
    $createdVisit = Visit::factory()->create();

    expect($readyVisit->status)->toBe(VisitStatus::CheckedIn)
        ->and($readyVisit->isReadyForDoctorConsultation())->toBeTrue()
        ->and($readyVisit->isInConsultation())->toBeFalse()
        ->and($readyVisit->workflowMessage())->toBe('Ready for Doctor consultation')
        ->and(Visit::query()->readyForDoctorConsultation()->pluck('id')->all())->toBe([$readyVisit->id])
        ->and(Visit::query()->inConsultation()->count())->toBe(0)
        ->and($readyVisit->consultation)->toBeNull()
        ->and($readyVisit->procedureBillingHandoff)->toBeNull()
        ->and($createdVisit->isReadyForDoctorConsultation())->toBeFalse()
        ->and(Consultation::query()->count())->toBe(0)
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0);
});

it('projects in-progress and finalized Consultation state without changing Visit status', function () {
    $consultation = Consultation::factory()->create();
    $visit = $consultation->visit->fresh(['checkIn', 'consultation']);

    expect($visit->status)->toBe(VisitStatus::CheckedIn)
        ->and($visit->isReadyForDoctorConsultation())->toBeFalse()
        ->and($visit->isInConsultation())->toBeTrue()
        ->and($visit->workflowMessage())->toBe('Consultation in progress')
        ->and(Visit::query()->readyForDoctorConsultation()->count())->toBe(0)
        ->and(Visit::query()->inConsultation()->pluck('id')->all())->toBe([$visit->id])
        ->and($visit->procedureBillingHandoff)->toBeNull();

    $finalizedConsultation = Consultation::factory()->createFinalizedFixture();
    $finalizedVisit = $finalizedConsultation->visit->fresh(['checkIn', 'consultation']);

    expect($finalizedVisit->status)->toBe(VisitStatus::CheckedIn)
        ->and($finalizedVisit->isReadyForDoctorConsultation())->toBeFalse()
        ->and($finalizedVisit->isInConsultation())->toBeFalse()
        ->and($finalizedVisit->workflowMessage())->toBe('Consultation completed')
        ->and($finalizedVisit->procedureBillingHandoff)->toBeNull()
        ->and(ProcedureBillingHandoff::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('exposes only the Doctor queue start and assessment workspace routes', function () {
    expect(Route::has('clinical.consultations.index'))->toBeTrue()
        ->and(Route::has('clinical.consultations.create'))->toBeTrue()
        ->and(Route::has('clinical.consultations.store'))->toBeTrue()
        ->and(Route::has('clinical.consultations.show'))->toBeTrue()
        ->and(Route::has('clinical.consultations.update'))->toBeTrue()
        ->and(Route::has('clinical.consultations.finalize'))->toBeFalse()
        ->and(Route::has('procedures.store'))->toBeFalse();
});
