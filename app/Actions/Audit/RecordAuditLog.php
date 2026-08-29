<?php

namespace App\Actions\Audit;

use App\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RecordAuditLog
{
    /** @var list<string> */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'credential',
        'csrf',
        'password',
        'remember',
        'secret',
        'session',
        'token',
    ];

    /**
     * @param  array<string, mixed>  $beforeValues
     * @param  array<string, mixed>  $afterValues
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        ?User $actor,
        AuditAction $action,
        Model $subject,
        array $beforeValues = [],
        array $afterValues = [],
        array $metadata = [],
    ): AuditLog {
        $auditLog = new AuditLog;
        $auditLog->actor_id = $actor?->getKey();
        $auditLog->action = $action;
        $auditLog->subject_type = $subject->getMorphClass();
        $auditLog->subject_id = $subject->getKey();
        $auditLog->before_values = $this->nullableSanitizedValues($beforeValues);
        $auditLog->after_values = $this->nullableSanitizedValues($afterValues);
        $auditLog->metadata = $this->nullableSanitizedValues($metadata);
        $auditLog->save();

        return $auditLog;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function nullableSanitizedValues(array $values): ?array
    {
        $sanitizedValues = $this->sanitize($values);

        return $sanitizedValues === [] ? null : $sanitizedValues;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $sanitizedValues = [];

        foreach ($values as $key => $value) {
            if (Str::contains(Str::lower((string) $key), self::SENSITIVE_KEY_FRAGMENTS)) {
                continue;
            }

            $sanitizedValues[$key] = is_array($value)
                ? $this->sanitize($value)
                : $value;
        }

        return $sanitizedValues;
    }
}
