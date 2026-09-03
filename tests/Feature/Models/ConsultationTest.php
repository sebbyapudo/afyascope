<?php

use App\AsaClassification;
use App\ConsultationStatus;
use App\Models\Consultation;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\StaffRole;
use App\VisitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('relates one Consultation to its checked-in Visit and responsible Doctor', function () {
    $visit = VisitCheckIn::factory()->create()->visit;
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $consultation = Consultation::factory()
        ->for($visit)
        ->for($doctor, 'doctor')
        ->create();

    expect($consultation->visit->is($visit))->toBeTrue()
        ->and($visit->fresh()->consultation->is($consultation))->toBeTrue()
        ->and($consultation->doctor->is($doctor))->toBeTrue()
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->finalized_at)->toBeNull()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and(Schema::hasColumns('consultations', [
            'presenting_complaint',
            'relevant_history',
            'current_medications',
            'allergies',
            'examination_findings',
            'asa_classification',
            'assessment_impression',
            'plan_notes',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('consultations', 'patient_id'))->toBeFalse()
        ->and(Schema::hasColumn('consultations', 'procedure_id'))->toBeFalse()
        ->and(Schema::hasTable('consultation_assessments'))->toBeFalse();
});

it('generates an immutable server-controlled sequential reference and start state', function () {
    $consultation = Consultation::factory()->create([
        'id' => 47,
        'consultation_number' => 'CON-SUPPLIED',
        'status' => ConsultationStatus::Finalized,
        'started_at' => '2020-01-01 00:00:00',
        'finalized_at' => '2020-01-01 01:00:00',
    ]);

    expect($consultation->consultation_number)->toBe('CON-000047')
        ->and($consultation->status)->toBe(ConsultationStatus::InProgress)
        ->and($consultation->started_at->year)->toBeGreaterThan(2020)
        ->and($consultation->finalized_at)->toBeNull();

    $consultation->consultation_number = 'CON-CHANGED';

    expect(fn () => $consultation->save())->toThrow(LogicException::class);
});

it('produces unique references during burst-style creation', function () {
    $consultations = Consultation::factory()->count(20)->create();

    expect($consultations->pluck('consultation_number')->unique())->toHaveCount(20);

    $consultations->each(function (Consultation $consultation): void {
        expect($consultation->consultation_number)->toBe(
            'CON-'.str_pad((string) $consultation->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('enforces one Consultation per Visit and restrictive ownership foreign keys', function () {
    $consultation = Consultation::factory()->create();

    expect(fn () => Consultation::factory()
        ->for($consultation->visit)
        ->create())->toThrow(QueryException::class);

    expect(fn () => DB::table('consultations')->insert([
        'visit_id' => 999_999,
        'doctor_user_id' => $consultation->doctor_user_id,
        'consultation_number' => 'CON-ORPHAN',
        'status' => ConsultationStatus::InProgress->value,
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => $consultation->visit->delete())->toThrow(QueryException::class)
        ->and(fn () => $consultation->doctor->delete())->toThrow(QueryException::class);
});

it('requires an actual checked-in Visit and an active Doctor', function () {
    $createdVisit = Visit::factory()->create();
    $doctor = User::factory()->forRole(StaffRole::Doctor)->create();
    $receptionist = User::factory()->forRole(StaffRole::Receptionist)->create();
    $inactiveDoctor = User::factory()->forRole(StaffRole::Doctor)->inactive()->create();

    expect(fn () => Consultation::factory()
        ->for($createdVisit)
        ->for($doctor, 'doctor')
        ->create())->toThrow(LogicException::class, 'A Consultation requires a checked-in Visit.');

    expect(fn () => Consultation::factory()
        ->for($receptionist, 'doctor')
        ->create())->toThrow(LogicException::class, 'A Consultation requires an active Doctor.');

    expect(fn () => Consultation::factory()
        ->for($inactiveDoctor, 'doctor')
        ->create())->toThrow(LogicException::class, 'A Consultation requires an active Doctor.');

    expect(Consultation::query()->count())->toBe(0);
});

it('defines the minimal lifecycle while reserving transitions for authoritative actions', function () {
    $consultation = Consultation::factory()->create();

    expect(ConsultationStatus::cases())->toBe([
        ConsultationStatus::InProgress,
        ConsultationStatus::Finalized,
    ]);

    $consultation->finalized_at = now()->subDay();

    expect(fn () => $consultation->save())->toThrow(
        LogicException::class,
        'Consultation lifecycle changes require their authoritative workflow action.',
    );

    $consultation->refresh();
    $consultation->status = ConsultationStatus::Finalized;

    expect(fn () => $consultation->save())->toThrow(
        LogicException::class,
        'Consultation lifecycle changes require their authoritative workflow action.',
    );

    $finalizedConsultation = Consultation::factory()->createFinalizedFixture();

    expect($finalizedConsultation->isFinalized())->toBeTrue()
        ->and($finalizedConsultation->finalized_at)->not->toBeNull();

    $finalizedConsultation->status = ConsultationStatus::InProgress;

    expect(fn () => $finalizedConsultation->save())->toThrow(
        LogicException::class,
        'Finalized Consultations cannot be changed.',
    );
});

it('defines only the supported optional ASA classifications', function () {
    expect(AsaClassification::cases())->toBe([
        AsaClassification::One,
        AsaClassification::Two,
        AsaClassification::Three,
        AsaClassification::Four,
        AsaClassification::Five,
        AsaClassification::Six,
    ]);
});
