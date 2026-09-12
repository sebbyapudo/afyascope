<?php

namespace App\Actions\Audit;

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class PresentAuditLog
{
    /** @var list<string> */
    private const SEARCHABLE_REFERENCE_FIELDS = [
        'appointment_number',
        'bill_number',
        'check_in_number',
        'clearance_number',
        'completion_source_reference',
        'consultation_number',
        'decision_number',
        'discharge_number',
        'patient_number',
        'payment_number',
        'procedure_number',
        'readiness_number',
        'receipt_number',
        'recovery_number',
        'visit_number',
    ];

    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'amount_minor' => 'Amount (minor units)',
        'appointment_number' => 'Appointment reference',
        'bill_number' => 'Bill reference',
        'category' => 'Category',
        'check_in_number' => 'Check-in reference',
        'clearance_number' => 'Clearance reference',
        'clinical_concern_requires_escalation' => 'Clinical concern requires escalation',
        'completed_at' => 'Completed at',
        'completion_source_reference' => 'Completion source reference',
        'completion_source_type' => 'Completion source type',
        'consultation_number' => 'Consultation reference',
        'criteria_met' => 'Readiness criteria met',
        'currency' => 'Currency',
        'decision_number' => 'Decision reference',
        'discharge_number' => 'Discharge reference',
        'discharged_at' => 'Discharged at',
        'email' => 'Email address',
        'escalated_at' => 'Escalated at',
        'is_active' => 'Status',
        'method' => 'Payment method',
        'name' => 'Name',
        'occurred_at' => 'Occurred at',
        'outcome' => 'Outcome',
        'patient_number' => 'Patient reference',
        'payment_number' => 'Payment reference',
        'procedure_number' => 'Procedure reference',
        'readiness_number' => 'Readiness reference',
        'receipt_number' => 'Receipt reference',
        'recorded_at' => 'Recorded at',
        'recovery_number' => 'Recovery reference',
        'recovery_status' => 'Recovery status',
        'resolution' => 'Resolution',
        'resolved_at' => 'Resolved at',
        'role' => 'Role',
        'scheduled_at' => 'Scheduled at',
        'status' => 'Status',
        'type' => 'Type',
        'unit_price_minor' => 'Unit price (minor units)',
        'visit_number' => 'Visit reference',
    ];

    /**
     * @return list<string>
     */
    public static function searchableReferenceFields(): array
    {
        return self::SEARCHABLE_REFERENCE_FIELDS;
    }

    /**
     * @return array{
     *     id: int,
     *     occurredAt: string,
     *     actor: array{id: int, name: string, email: string, isActive: bool, roleAtEvent: null}|null,
     *     action: array{value: string, label: string},
     *     subject: array{type: string, reference: string|null, internalId: int},
     *     changes: list<array{field: string, label: string, before: string|int|bool|null, after: string|int|bool|null}>
     * }
     */
    public function summary(AuditLog $auditLog): array
    {
        $actor = $auditLog->actor;

        return [
            'id' => $auditLog->id,
            'occurredAt' => $auditLog->created_at->toIso8601String(),
            'actor' => $actor instanceof User ? [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
                'isActive' => $actor->is_active,
                'roleAtEvent' => null,
            ] : null,
            'action' => [
                'value' => $auditLog->action->value,
                'label' => $auditLog->action->displayName(),
            ],
            'subject' => [
                'type' => $this->subjectTypeLabel($auditLog->subject_type),
                'reference' => $this->subjectReference($auditLog),
                'internalId' => $auditLog->subject_id,
            ],
            'changes' => $this->changes($auditLog),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     occurredAt: string,
     *     actor: array{id: int, name: string, email: string, isActive: bool, roleAtEvent: null}|null,
     *     action: array{value: string, label: string},
     *     subject: array{type: string, reference: string|null, internalId: int},
     *     changes: list<array{field: string, label: string, before: string|int|bool|null, after: string|int|bool|null}>,
     *     metadata: list<array{field: string, label: string, value: string|int|bool|null}>
     * }
     */
    public function detail(AuditLog $auditLog): array
    {
        return [
            ...$this->summary($auditLog),
            'metadata' => $this->metadata($auditLog),
        ];
    }

    /**
     * @return list<array{field: string, label: string, before: string|int|bool|null, after: string|int|bool|null}>
     */
    private function changes(AuditLog $auditLog): array
    {
        $beforeValues = $auditLog->before_values ?? [];
        $afterValues = $auditLog->after_values ?? [];
        $changes = [];

        foreach ($this->visibleFields($auditLog->action) as $field) {
            if (! array_key_exists($field, $beforeValues) && ! array_key_exists($field, $afterValues)) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => self::FIELD_LABELS[$field] ?? Str::headline($field),
                'before' => $this->safeScalar($field, $beforeValues[$field] ?? null),
                'after' => $this->safeScalar($field, $afterValues[$field] ?? null),
            ];
        }

        return $changes;
    }

    /**
     * @return list<array{field: string, label: string, value: string|int|bool|null}>
     */
    private function metadata(AuditLog $auditLog): array
    {
        if ($auditLog->action !== AuditAction::ServicePriceUpdated) {
            return [];
        }

        $metadata = $auditLog->metadata ?? [];

        if (! array_key_exists('currency', $metadata)) {
            return [];
        }

        return [[
            'field' => 'currency',
            'label' => self::FIELD_LABELS['currency'],
            'value' => $this->safeScalar('currency', $metadata['currency']),
        ]];
    }

    /**
     * @return list<string>
     */
    private function visibleFields(AuditAction $action): array
    {
        return match ($action) {
            AuditAction::AdministratorBootstrapped,
            AuditAction::StaffCreated,
            AuditAction::StaffUpdated => ['name', 'email', 'role', 'is_active'],
            AuditAction::PatientRegistered => ['patient_number'],
            AuditAction::PatientUpdated => [],
            AuditAction::VisitCreated => ['visit_number', 'occurred_at', 'status'],
            AuditAction::VisitCheckedIn => ['check_in_number', 'visit_number', 'clearance_number'],
            AuditAction::VisitCompleted => [
                'visit_number',
                'completion_source_type',
                'completion_source_reference',
                'completed_at',
                'status',
            ],
            AuditAction::AppointmentCreated => ['appointment_number', 'scheduled_at', 'status'],
            AuditAction::AppointmentRescheduled => ['scheduled_at'],
            AuditAction::AppointmentCancelled,
            AuditAction::AppointmentNoShow => ['status'],
            AuditAction::AppointmentVisitLinked => ['visit_number'],
            AuditAction::BillCreated => ['bill_number', 'type', 'status', 'amount_minor'],
            AuditAction::PaymentRecorded => ['payment_number', 'bill_number', 'amount_minor', 'method'],
            AuditAction::ReceiptIssued => ['receipt_number'],
            AuditAction::ConsultationFinancialCleared,
            AuditAction::ProcedureFinancialCleared => ['clearance_number', 'bill_number', 'receipt_number'],
            AuditAction::ConsultationStarted,
            AuditAction::ConsultationAssessmentUpdated => ['consultation_number', 'visit_number'],
            AuditAction::ConsultationProcedureDecided => [
                'decision_number',
                'consultation_number',
                'visit_number',
                'outcome',
            ],
            AuditAction::NursingPreparationStarted,
            AuditAction::NursingReadinessCompleted => ['readiness_number', 'status'],
            AuditAction::ProcedureStarted,
            AuditAction::ProcedureDocumentationUpdated,
            AuditAction::ProcedureCompleted => ['procedure_number', 'status'],
            AuditAction::RecoveryStarted => ['recovery_number', 'visit_number', 'procedure_number', 'status'],
            AuditAction::RecoveryObservationRecorded => ['recovery_number', 'recorded_at'],
            AuditAction::RecoveryReadinessAssessed => [
                'recovery_number',
                'criteria_met',
                'clinical_concern_requires_escalation',
            ],
            AuditAction::RecoveryEscalated => ['recovery_number', 'status', 'escalated_at'],
            AuditAction::RecoveryEscalationResolved => ['recovery_number', 'resolution', 'resolved_at'],
            AuditAction::RecoveryDischarged => [
                'discharge_number',
                'recovery_number',
                'recovery_status',
                'discharged_at',
            ],
            AuditAction::ServiceCreated,
            AuditAction::ServiceUpdated => ['name', 'category', 'unit_price_minor', 'is_active'],
            AuditAction::ServicePriceUpdated => ['unit_price_minor'],
            AuditAction::ServiceActivated,
            AuditAction::ServiceDeactivated => ['is_active'],
        };
    }

    private function safeScalar(string $field, mixed $value): string|int|bool|null
    {
        if ($field === 'role' && is_array($value)) {
            $roleName = $value['name'] ?? null;

            return is_string($roleName) ? $roleName : null;
        }

        return is_string($value) || is_int($value) || is_bool($value) ? $value : null;
    }

    private function subjectReference(AuditLog $auditLog): ?string
    {
        $referenceFields = match (class_basename($auditLog->subject_type)) {
            'Appointment' => ['appointment_number'],
            'Bill' => ['bill_number'],
            'Consultation' => ['consultation_number'],
            'FinancialClearance' => ['clearance_number'],
            'Patient' => ['patient_number'],
            'Payment' => ['payment_number'],
            'PreProcedureReadiness' => ['readiness_number'],
            'ProcedureDecision' => ['decision_number'],
            'ProcedureRecord' => ['procedure_number'],
            'Receipt' => ['receipt_number'],
            'RecoveryDischarge' => ['discharge_number'],
            'RecoveryEpisode' => ['recovery_number'],
            'RecoveryEscalation', 'RecoveryObservation', 'RecoveryReadinessAssessment' => ['recovery_number'],
            'ServiceCatalogItem' => ['name'],
            'User' => ['name', 'email'],
            'Visit' => ['visit_number'],
            'VisitCheckIn' => ['check_in_number'],
            default => self::SEARCHABLE_REFERENCE_FIELDS,
        };

        foreach ($referenceFields as $field) {
            $value = $this->snapshotValue($auditLog, $field);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function snapshotValue(AuditLog $auditLog, string $field): mixed
    {
        foreach ([$auditLog->after_values, $auditLog->before_values, $auditLog->metadata] as $values) {
            if (is_array($values) && array_key_exists($field, $values)) {
                return $values[$field];
            }
        }

        return null;
    }

    private function subjectTypeLabel(string $subjectType): string
    {
        return match (class_basename($subjectType)) {
            'FinancialClearance' => 'Financial clearance',
            'PreProcedureReadiness' => 'Pre-procedure readiness',
            'ProcedureDecision' => 'Procedure decision',
            'ProcedureRecord' => 'Procedure record',
            'RecoveryDischarge' => 'Recovery discharge',
            'RecoveryEpisode' => 'Recovery episode',
            'RecoveryEscalation' => 'Recovery escalation',
            'RecoveryObservation' => 'Recovery observation',
            'RecoveryReadinessAssessment' => 'Recovery readiness assessment',
            'ServiceCatalogItem' => 'Service catalog item',
            'User' => 'Staff account',
            'VisitCheckIn' => 'Visit check-in',
            default => Str::headline(class_basename($subjectType)),
        };
    }
}
