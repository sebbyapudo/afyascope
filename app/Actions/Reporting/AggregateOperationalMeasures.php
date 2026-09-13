<?php

namespace App\Actions\Reporting;

use App\Models\Consultation;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\Visit;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\VisitStatus;
use stdClass;

final class AggregateOperationalMeasures
{
    /** @return array{visits: array{occurred: int, active: int, completed: int}, milestones: array{consultationsStarted: int, procedureRequired: int, noProcedure: int, proceduresCompleted: int, recoveriesStarted: int, dischargesCompleted: int}} */
    public function handle(ReportingPeriod $period): array
    {
        $visitMetrics = $this->visitMetrics($period);
        $decisionMetrics = $this->decisionMetrics($period);

        return [
            'visits' => [
                'occurred' => $this->integer($visitMetrics, 'occurred'),
                'active' => $this->integer($visitMetrics, 'active'),
                'completed' => Visit::query()
                    ->where('status', VisitStatus::Completed->value)
                    ->whereBetween('completed_at', $period->bounds())
                    ->count(),
            ],
            'milestones' => [
                'consultationsStarted' => Consultation::query()
                    ->whereBetween('started_at', $period->bounds())
                    ->count(),
                'procedureRequired' => $this->integer($decisionMetrics, 'procedure_required'),
                'noProcedure' => $this->integer($decisionMetrics, 'no_procedure'),
                'proceduresCompleted' => ProcedureRecord::query()
                    ->where('status', ProcedureRecordStatus::Completed->value)
                    ->whereBetween('completed_at', $period->bounds())
                    ->count(),
                'recoveriesStarted' => RecoveryEpisode::query()
                    ->whereBetween('started_at', $period->bounds())
                    ->count(),
                'dischargesCompleted' => RecoveryDischarge::query()
                    ->whereBetween('discharged_at', $period->bounds())
                    ->count(),
            ],
        ];
    }

    private function visitMetrics(ReportingPeriod $period): ?stdClass
    {
        return Visit::query()
            ->whereBetween('occurred_at', $period->bounds())
            ->toBase()
            ->selectRaw('COUNT(*) as occurred')
            ->selectRaw('COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) as active', [
                VisitStatus::Completed->value,
            ])
            ->first();
    }

    private function decisionMetrics(ReportingPeriod $period): ?stdClass
    {
        return ProcedureDecision::query()
            ->whereBetween('decided_at', $period->bounds())
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END), 0) as procedure_required', [
                ProcedureDecisionOutcome::ProcedureRequired->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN outcome = ? THEN 1 ELSE 0 END), 0) as no_procedure', [
                ProcedureDecisionOutcome::NoProcedure->value,
            ])
            ->first();
    }

    private function integer(?stdClass $metrics, string $property): int
    {
        if (! $metrics instanceof stdClass || ! property_exists($metrics, $property)) {
            return 0;
        }

        return (int) $metrics->{$property};
    }
}
