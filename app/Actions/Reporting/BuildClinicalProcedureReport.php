<?php

namespace App\Actions\Reporting;

use App\Models\User;
use App\StaffPermission;
use Illuminate\Support\Facades\Gate;

final class BuildClinicalProcedureReport
{
    public function __construct(private AggregateClinicalProcedureMeasures $aggregateClinicalProcedureMeasures) {}

    /** @return array{period: array{fromDate: string, throughDate: string, timezone: string}, consultationDecision: array{consultationsStarted: int, procedureRequired: int, noProcedure: int}, procedure: array{started: int, completed: int}, preparation: array{started: int, completed: int}, recovery: array{started: int, completed: int, discharged: int}, escalation: array{raised: int, resolved: int}, terminalOutcomes: array{procedurePathVisitsCompleted: int, noProcedureVisitsCompleted: int}, procedureDistribution: list<array{procedureName: string, procedureRequiredDecisions: int, proceduresCompleted: int}>} */
    public function handle(User $actor, ReportingPeriod $period): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::ReportsClinicalView);
        $timezone = config('app.timezone');

        return [
            'period' => [
                'fromDate' => $period->startsAt->toDateString(),
                'throughDate' => $period->endsAt->toDateString(),
                'timezone' => is_string($timezone) ? $timezone : 'UTC',
            ],
            ...$this->aggregateClinicalProcedureMeasures->handle($period),
        ];
    }
}
