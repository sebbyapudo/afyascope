<?php

namespace App\Actions\Reporting;

use App\Models\Consultation;
use App\Models\PreProcedureReadiness;
use App\Models\ProcedureDecision;
use App\Models\ProcedureRecord;
use App\Models\RecoveryDischarge;
use App\Models\RecoveryEpisode;
use App\Models\RecoveryEscalation;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\RecoveryEpisodeStatus;
use App\RecoveryEscalationStatus;
use App\VisitStatus;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AggregateClinicalProcedureMeasures
{
    /** @return array{consultationDecision: array{consultationsStarted: int, procedureRequired: int, noProcedure: int}, procedure: array{started: int, completed: int}, preparation: array{started: int, completed: int}, recovery: array{started: int, completed: int, discharged: int}, escalation: array{raised: int, resolved: int}, terminalOutcomes: array{procedurePathVisitsCompleted: int, noProcedureVisitsCompleted: int}, procedureDistribution: list<array{procedureName: string, procedureRequiredDecisions: int, proceduresCompleted: int}>} */
    public function handle(ReportingPeriod $period): array
    {
        $decisionMetrics = $this->decisionMetrics($period);
        $procedureMetrics = $this->procedureMetrics($period);
        $preparationMetrics = $this->preparationMetrics($period);
        $recoveryMetrics = $this->recoveryMetrics($period);
        $escalationMetrics = $this->escalationMetrics($period);
        $terminalOutcomeMetrics = $this->terminalOutcomeMetrics($period);

        return [
            'consultationDecision' => [
                'consultationsStarted' => Consultation::query()->whereBetween('started_at', $period->bounds())->count(),
                'procedureRequired' => $this->integer($decisionMetrics, 'procedure_required'),
                'noProcedure' => $this->integer($decisionMetrics, 'no_procedure'),
            ],
            'procedure' => [
                'started' => $this->integer($procedureMetrics, 'started'),
                'completed' => $this->integer($procedureMetrics, 'completed'),
            ],
            'preparation' => [
                'started' => $this->integer($preparationMetrics, 'started'),
                'completed' => $this->integer($preparationMetrics, 'completed'),
            ],
            'recovery' => [
                'started' => $this->integer($recoveryMetrics, 'started'),
                'completed' => $this->integer($recoveryMetrics, 'completed'),
                'discharged' => RecoveryDischarge::query()->whereBetween('discharged_at', $period->bounds())->count(),
            ],
            'escalation' => [
                'raised' => $this->integer($escalationMetrics, 'raised'),
                'resolved' => $this->integer($escalationMetrics, 'resolved'),
            ],
            'terminalOutcomes' => [
                'procedurePathVisitsCompleted' => $this->integer($terminalOutcomeMetrics, 'procedure_path'),
                'noProcedureVisitsCompleted' => $this->integer($terminalOutcomeMetrics, 'no_procedure'),
            ],
            'procedureDistribution' => $this->procedureDistribution($period),
        ];
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

    private function procedureMetrics(ReportingPeriod $period): ?stdClass
    {
        return ProcedureRecord::query()
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN started_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as started', $period->bounds())
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as completed', [
                ProcedureRecordStatus::Completed->value,
                ...$period->bounds(),
            ])
            ->first();
    }

    private function preparationMetrics(ReportingPeriod $period): ?stdClass
    {
        return PreProcedureReadiness::query()
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN started_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as started', $period->bounds())
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as completed', [
                PreProcedureReadinessStatus::Ready->value,
                ...$period->bounds(),
            ])
            ->first();
    }

    private function recoveryMetrics(ReportingPeriod $period): ?stdClass
    {
        return RecoveryEpisode::query()
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN started_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as started', $period->bounds())
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND completed_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as completed', [
                RecoveryEpisodeStatus::Completed->value,
                ...$period->bounds(),
            ])
            ->first();
    }

    private function escalationMetrics(ReportingPeriod $period): ?stdClass
    {
        return RecoveryEscalation::query()
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN escalated_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as raised', $period->bounds())
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND resolved_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as resolved', [
                RecoveryEscalationStatus::Resolved->value,
                ...$period->bounds(),
            ])
            ->first();
    }

    private function terminalOutcomeMetrics(ReportingPeriod $period): ?stdClass
    {
        return DB::table('visits')
            ->join('procedure_decisions', 'procedure_decisions.visit_id', '=', 'visits.id')
            ->where('visits.status', VisitStatus::Completed->value)
            ->whereBetween('visits.completed_at', $period->bounds())
            ->selectRaw('COALESCE(SUM(CASE WHEN procedure_decisions.outcome = ? THEN 1 ELSE 0 END), 0) as procedure_path', [
                ProcedureDecisionOutcome::ProcedureRequired->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN procedure_decisions.outcome = ? THEN 1 ELSE 0 END), 0) as no_procedure', [
                ProcedureDecisionOutcome::NoProcedure->value,
            ])
            ->first();
    }

    /** @return list<array{procedureName: string, procedureRequiredDecisions: int, proceduresCompleted: int}> */
    private function procedureDistribution(ReportingPeriod $period): array
    {
        $decisionActivity = DB::table('procedure_decisions')
            ->where('outcome', ProcedureDecisionOutcome::ProcedureRequired->value)
            ->whereBetween('decided_at', $period->bounds())
            ->select('service_catalog_item_id')
            ->selectRaw('1 as decision_count, 0 as completed_count');

        $completedProcedureActivity = DB::table('procedure_records')
            ->where('status', ProcedureRecordStatus::Completed->value)
            ->whereBetween('completed_at', $period->bounds())
            ->select('service_catalog_item_id')
            ->selectRaw('0 as decision_count, 1 as completed_count');

        $distribution = DB::query()
            ->fromSub($decisionActivity->unionAll($completedProcedureActivity), 'procedure_activity')
            ->join('service_catalog_items', 'service_catalog_items.id', '=', 'procedure_activity.service_catalog_item_id')
            ->select('service_catalog_items.name as procedure_name')
            ->selectRaw('SUM(procedure_activity.decision_count) as decision_count')
            ->selectRaw('SUM(procedure_activity.completed_count) as completed_count')
            ->groupBy('service_catalog_items.id', 'service_catalog_items.name')
            ->orderBy('service_catalog_items.name')
            ->orderBy('service_catalog_items.id')
            ->get()
            ->map(static fn (stdClass $row): array => [
                'procedureName' => (string) $row->procedure_name,
                'procedureRequiredDecisions' => (int) $row->decision_count,
                'proceduresCompleted' => (int) $row->completed_count,
            ])
            ->all();

        return array_values($distribution);
    }

    private function integer(?stdClass $metrics, string $property): int
    {
        if (! $metrics instanceof stdClass || ! property_exists($metrics, $property)) {
            return 0;
        }

        return (int) $metrics->{$property};
    }
}
