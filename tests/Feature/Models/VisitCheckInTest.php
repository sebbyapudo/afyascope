<?php

use App\Models\FinancialClearance;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use App\VisitStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('relates one immutable check-in to its Visit and Receptionist without duplicating Patient or financial data', function () {
    $financialClearance = FinancialClearance::factory()->create();
    $visit = $financialClearance->bill->visit;
    $receptionist = User::factory()->create();
    $visitCheckIn = VisitCheckIn::factory()
        ->for($visit)
        ->for($receptionist, 'checkedInBy')
        ->create();

    expect($visitCheckIn->visit->is($visit))->toBeTrue()
        ->and($visit->fresh()->checkIn->is($visitCheckIn))->toBeTrue()
        ->and($visitCheckIn->checkedInBy->is($receptionist))->toBeTrue()
        ->and($visit->fresh()->status)->toBe(VisitStatus::CheckedIn)
        ->and(Schema::hasColumn('visit_check_ins', 'patient_id'))->toBeFalse()
        ->and(Schema::hasColumn('visit_check_ins', 'bill_id'))->toBeFalse()
        ->and(Schema::hasColumn('visit_check_ins', 'payment_id'))->toBeFalse()
        ->and(Schema::hasColumn('visit_check_ins', 'financial_clearance_id'))->toBeFalse();
});

it('generates a server-controlled sequential reference and immutable business fields', function () {
    $financialClearance = FinancialClearance::factory()->create();
    $visit = $financialClearance->bill->visit;
    $receptionist = User::factory()->create();
    $visitCheckIn = VisitCheckIn::factory()
        ->for($visit)
        ->for($receptionist, 'checkedInBy')
        ->create([
            'id' => 47,
            'check_in_number' => 'CHK-SUPPLIED',
            'checked_in_at' => '2020-01-01 00:00:00',
        ]);

    expect($visitCheckIn->check_in_number)->toBe('CHK-000047')
        ->and($visitCheckIn->checked_in_at->year)->toBeGreaterThan(2020);

    $visitCheckIn->check_in_number = 'CHK-CHANGED';

    expect(fn () => $visitCheckIn->save())->toThrow(LogicException::class);
});

it('produces unique references during burst-style creation', function () {
    $checkIns = VisitCheckIn::factory()->count(20)->create();

    expect($checkIns->pluck('check_in_number')->unique())->toHaveCount(20);

    $checkIns->each(function (VisitCheckIn $visitCheckIn): void {
        expect($visitCheckIn->check_in_number)->toBe(
            'CHK-'.str_pad((string) $visitCheckIn->id, 6, '0', STR_PAD_LEFT),
        );
    });
});

it('uses database uniqueness and foreign keys as concurrency and history backstops', function () {
    $visitCheckIn = VisitCheckIn::factory()->create();

    expect(fn () => DB::table('visit_check_ins')->insert([
        'visit_id' => $visitCheckIn->visit_id,
        'check_in_number' => 'CHK-DUPLICATE-VISIT',
        'checked_in_by_user_id' => $visitCheckIn->checked_in_by_user_id,
        'checked_in_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => $visitCheckIn->visit->delete())->toThrow(QueryException::class)
        ->and(fn () => $visitCheckIn->checkedInBy->delete())->toThrow(QueryException::class);
});

it('rejects malformed direct creation and direct Visit status forgery without residue', function () {
    $unbilledVisit = Visit::factory()->create();
    $receptionist = User::factory()->create();

    expect(fn () => VisitCheckIn::factory()
        ->for($unbilledVisit)
        ->for($receptionist, 'checkedInBy')
        ->create())->toThrow(LogicException::class);

    $unbilledVisit->status = VisitStatus::CheckedIn;

    expect(fn () => $unbilledVisit->save())->toThrow(LogicException::class);

    expect(VisitCheckIn::query()->count())->toBe(0)
        ->and($unbilledVisit->fresh()->status)->toBe(VisitStatus::Created);
});
