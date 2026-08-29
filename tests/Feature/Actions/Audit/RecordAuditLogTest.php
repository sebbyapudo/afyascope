<?php

use App\Actions\Audit\RecordAuditLog;
use App\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('records an actor action subject and meaningful before and after values', function () {
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();
    $subject = User::factory()->forRole(StaffRole::Receptionist)->create();

    $auditLog = app(RecordAuditLog::class)->handle(
        actor: $actor,
        action: AuditAction::StaffUpdated,
        subject: $subject,
        beforeValues: ['name' => 'Previous name'],
        afterValues: ['name' => 'Updated name'],
        metadata: ['source' => 'staff-administration'],
    );

    expect($auditLog->actor->is($actor))->toBeTrue()
        ->and($auditLog->subject->is($subject))->toBeTrue()
        ->and($auditLog->action)->toBe(AuditAction::StaffUpdated)
        ->and($auditLog->before_values)->toBe(['name' => 'Previous name'])
        ->and($auditLog->after_values)->toBe(['name' => 'Updated name'])
        ->and($auditLog->metadata)->toBe(['source' => 'staff-administration']);
});

it('recursively removes security-sensitive values from every audit data section', function () {
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();
    $subject = User::factory()->create();

    $auditLog = app(RecordAuditLog::class)->handle(
        actor: $actor,
        action: AuditAction::StaffUpdated,
        subject: $subject,
        beforeValues: [
            'email' => 'before@example.com',
            'password_hash' => 'before-password-hash',
        ],
        afterValues: [
            'email' => 'after@example.com',
            'security' => [
                'password' => 'plaintext-password',
                'remember_token' => 'remember-me',
                'safe_note' => 'retained',
            ],
        ],
        metadata: [
            'password_reset_token' => 'reset-token',
            'session_id' => 'session-identifier',
            'source' => 'staff-administration',
        ],
    );

    expect($auditLog->before_values)->toBe(['email' => 'before@example.com'])
        ->and($auditLog->after_values)->toBe([
            'email' => 'after@example.com',
            'security' => ['safe_note' => 'retained'],
        ])
        ->and($auditLog->metadata)->toBe(['source' => 'staff-administration'])
        ->and($auditLog->getRawOriginal('before_values'))->not->toContain('before-password-hash')
        ->and($auditLog->getRawOriginal('after_values'))->not->toContain('plaintext-password')
        ->and($auditLog->getRawOriginal('metadata'))->not->toContain('reset-token');
});

it('keeps historical actor attribution when a staff account becomes inactive', function () {
    $actor = User::factory()->forRole(StaffRole::Administrator)->create();
    $subject = User::factory()->create();
    $auditLog = app(RecordAuditLog::class)->handle(
        actor: $actor,
        action: AuditAction::StaffCreated,
        subject: $subject,
    );

    $actor->is_active = false;
    $actor->save();

    expect($auditLog->fresh()->actor->is($actor))->toBeTrue()
        ->and($auditLog->fresh()->actor->is_active)->toBeFalse();
});

it('guards historical audit attributes from mass assignment', function () {
    expect(fn () => AuditLog::query()->create([
        'action' => AuditAction::StaffCreated,
        'subject_type' => User::class,
        'subject_id' => 1,
    ]))->toThrow(MassAssignmentException::class);
});
