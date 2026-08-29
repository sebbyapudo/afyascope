<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Display the immutable audit history.
     */
    public function index(): Response
    {
        $auditLogs = AuditLog::query()
            ->with(['actor:id,name,email', 'subject'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25);

        return Inertia::render('audit-logs/index', [
            'auditLogs' => [
                'data' => $auditLogs->getCollection()
                    ->map(fn (AuditLog $auditLog): array => $this->auditLogData($auditLog))
                    ->values(),
                'pagination' => [
                    'currentPage' => $auditLogs->currentPage(),
                    'from' => $auditLogs->firstItem(),
                    'lastPage' => $auditLogs->lastPage(),
                    'to' => $auditLogs->lastItem(),
                    'total' => $auditLogs->total(),
                ],
            ],
        ]);
    }

    /**
     * @return array{
     *     id: int,
     *     occurredAt: string,
     *     actor: array{id: int, name: string, email: string}|null,
     *     action: array{value: string, label: string},
     *     subject: array{type: string, id: int, label: string},
     *     changes: list<array{field: string, label: string, before: mixed, after: mixed}>
     * }
     */
    private function auditLogData(AuditLog $auditLog): array
    {
        $actor = $auditLog->actor;

        return [
            'id' => $auditLog->id,
            'occurredAt' => $auditLog->created_at->toIso8601String(),
            'actor' => $actor instanceof User ? [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
            ] : null,
            'action' => [
                'value' => $auditLog->action->value,
                'label' => $auditLog->action->displayName(),
            ],
            'subject' => [
                'type' => class_basename($auditLog->subject_type),
                'id' => $auditLog->subject_id,
                'label' => $auditLog->subject instanceof User
                    ? $auditLog->subject->name
                    : class_basename($auditLog->subject_type).' #'.$auditLog->subject_id,
            ],
            'changes' => $this->changeData($auditLog),
        ];
    }

    /**
     * @return list<array{field: string, label: string, before: mixed, after: mixed}>
     */
    private function changeData(AuditLog $auditLog): array
    {
        $beforeValues = $auditLog->before_values ?? [];
        $afterValues = $auditLog->after_values ?? [];
        $fields = array_values(array_unique([
            ...array_keys($beforeValues),
            ...array_keys($afterValues),
        ]));

        return array_map(static fn (string $field): array => [
            'field' => $field,
            'label' => match ($field) {
                'is_active' => 'Account status',
                default => Str::headline($field),
            },
            'before' => $beforeValues[$field] ?? null,
            'after' => $afterValues[$field] ?? null,
        ], $fields);
    }
}
