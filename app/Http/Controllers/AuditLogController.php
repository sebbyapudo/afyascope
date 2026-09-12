<?php

namespace App\Http\Controllers;

use App\Actions\Audit\PresentAuditLog;
use App\AuditAction;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Consultation;
use App\Models\FinancialClearance;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\Receipt;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\Models\RecoveryObservation;
use App\Models\RecoveryReadinessAssessment;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCheckIn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /** @var array<class-string, string> */
    private const SUBJECT_TYPES = [
        Appointment::class => 'Appointment',
        Bill::class => 'Bill',
        Consultation::class => 'Consultation',
        FinancialClearance::class => 'Financial clearance',
        Patient::class => 'Patient',
        Payment::class => 'Payment',
        PreProcedureReadiness::class => 'Pre-procedure readiness',
        ProcedureDecision::class => 'Procedure decision',
        ProcedureRecord::class => 'Procedure record',
        Receipt::class => 'Receipt',
        RecoveryDischarge::class => 'Recovery discharge',
        RecoveryEpisode::class => 'Recovery episode',
        RecoveryEscalation::class => 'Recovery escalation',
        RecoveryObservation::class => 'Recovery observation',
        RecoveryReadinessAssessment::class => 'Recovery readiness assessment',
        ServiceCatalogItem::class => 'Service catalog item',
        User::class => 'Staff account',
        Visit::class => 'Visit',
        VisitCheckIn::class => 'Visit check-in',
    ];

    /**
     * Display the immutable audit history.
     */
    public function index(Request $request, PresentAuditLog $presentAuditLog): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', Rule::enum(AuditAction::class)],
            'actor' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', Rule::in(array_keys(self::SUBJECT_TYPES))],
            'subject_reference' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $event = $filters['event'] ?? null;
        $actor = trim((string) ($filters['actor'] ?? ''));
        $subjectType = $filters['subject_type'] ?? null;
        $subjectReference = trim((string) ($filters['subject_reference'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        $auditLogs = AuditLog::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $matchingActions = collect(AuditAction::cases())
                    ->filter(fn (AuditAction $action): bool => str_contains(
                        strtolower($action->value.' '.$action->displayName()),
                        strtolower($search),
                    ))
                    ->map(fn (AuditAction $action): string => $action->value)
                    ->all();

                $query->where(function (Builder $query) use ($search, $matchingActions): void {
                    if ($matchingActions !== []) {
                        $query->orWhereIn('action', $matchingActions);
                    }

                    $query->orWhereHas('actor', function (Builder $query) use ($search): void {
                        $query->where(function (Builder $query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                    });

                    $query->orWhere(function (Builder $query) use ($search): void {
                        $this->addSafeReferenceMatches($query, $search);
                    });
                });
            })
            ->when(is_string($event), fn (Builder $query) => $query->where('action', $event))
            ->when($actor !== '', function (Builder $query) use ($actor): void {
                $query->whereHas('actor', function (Builder $query) use ($actor): void {
                    $query->where(function (Builder $query) use ($actor): void {
                        $query->where('name', 'like', "%{$actor}%")
                            ->orWhere('email', 'like', "%{$actor}%");
                    });
                });
            })
            ->when(is_string($subjectType), fn (Builder $query) => $query->where('subject_type', $subjectType))
            ->when($subjectReference !== '', function (Builder $query) use ($subjectReference): void {
                $query->where(function (Builder $query) use ($subjectReference): void {
                    $this->addSafeReferenceMatches($query, $subjectReference);
                });
            })
            ->when(is_string($dateFrom), fn (Builder $query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when(is_string($dateTo), fn (Builder $query) => $query->whereDate('created_at', '<=', $dateTo))
            ->with('actor:id,name,email,is_active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('audit-logs/index', [
            'auditLogs' => [
                'data' => $auditLogs->getCollection()
                    ->map(fn (AuditLog $auditLog): array => $presentAuditLog->summary($auditLog))
                    ->values(),
                'pagination' => [
                    'currentPage' => $auditLogs->currentPage(),
                    'from' => $auditLogs->firstItem(),
                    'lastPage' => $auditLogs->lastPage(),
                    'to' => $auditLogs->lastItem(),
                    'total' => $auditLogs->total(),
                ],
            ],
            'filters' => [
                'q' => $search,
                'event' => is_string($event) ? $event : null,
                'actor' => $actor,
                'subjectType' => is_string($subjectType) ? $subjectType : null,
                'subjectReference' => $subjectReference,
                'dateFrom' => is_string($dateFrom) ? $dateFrom : null,
                'dateTo' => is_string($dateTo) ? $dateTo : null,
            ],
            'events' => array_map(static fn (AuditAction $action): array => [
                'value' => $action->value,
                'label' => $action->displayName(),
            ], AuditAction::cases()),
            'subjectTypes' => collect(self::SUBJECT_TYPES)
                ->map(fn (string $label, string $value): array => compact('value', 'label'))
                ->values(),
        ]);
    }

    /**
     * Display one immutable audit event through the safe review projection.
     */
    public function show(AuditLog $auditLog, PresentAuditLog $presentAuditLog): Response
    {
        $auditLog->loadMissing('actor:id,name,email,is_active');

        return Inertia::render('audit-logs/show', [
            'auditLog' => $presentAuditLog->detail($auditLog),
        ]);
    }

    /** @param Builder<AuditLog> $query */
    private function addSafeReferenceMatches(Builder $query, string $reference): void
    {
        foreach (PresentAuditLog::searchableReferenceFields() as $field) {
            $query->orWhere("before_values->{$field}", 'like', "%{$reference}%")
                ->orWhere("after_values->{$field}", 'like', "%{$reference}%");
        }
    }
}
