<?php

namespace App\Actions\Reporting;

use App\BillType;
use App\ConsultationStatus;
use App\Models\User;
use App\PreProcedureReadinessStatus;
use App\ProcedureDecisionOutcome;
use App\ProcedureRecordStatus;
use App\RecoveryEpisodeStatus;
use App\StaffPermission;
use App\VisitStatus;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BuildOperationalReport
{
    public function __construct(private AggregateOperationalMeasures $aggregateOperationalMeasures) {}

    /**
     * @return array{
     *     period: array{fromDate: string, throughDate: string, timezone: string},
     *     metrics: array{visits: array{occurred: int, active: int, completed: int}, milestones: array{consultationsStarted: int, procedureRequired: int, noProcedure: int, proceduresCompleted: int, recoveriesStarted: int, dischargesCompleted: int}},
     *     stages: list<array{key: string, label: string, count: int}>
     * }
     */
    public function handle(User $actor, ReportingPeriod $period): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::ReportsOperationalView);
        $timezone = config('app.timezone');

        return [
            'period' => [
                'fromDate' => $period->startsAt->toDateString(),
                'throughDate' => $period->endsAt->toDateString(),
                'timezone' => is_string($timezone) ? $timezone : 'UTC',
            ],
            'metrics' => $this->aggregateOperationalMeasures->handle($period),
            'stages' => $this->stageDistribution($period),
        ];
    }

    /** @return list<array{key: string, label: string, count: int}> */
    private function stageDistribution(ReportingPeriod $period): array
    {
        $stageProjectionSql = <<<'SQL'
            CASE
                WHEN visits.status = ? THEN ?
                WHEN recovery_episodes.status = ? THEN ?
                WHEN recovery_episodes.status = ? AND recovery_escalations.id IS NOT NULL THEN ?
                WHEN recovery_episodes.status = ? THEN ?
                WHEN procedure_records.status = ? THEN ?
                WHEN procedure_records.status = ? THEN ?
                WHEN pre_procedure_readinesses.status = ? THEN ?
                WHEN pre_procedure_readinesses.status = ? THEN ?
                WHEN procedure_decisions.outcome = ? AND procedure_bills.id IS NULL THEN ?
                WHEN procedure_decisions.outcome = ? AND (procedure_payments.id IS NULL OR procedure_receipts.id IS NULL) THEN ?
                WHEN procedure_decisions.outcome = ? AND procedure_clearances.id IS NULL THEN ?
                WHEN procedure_decisions.outcome = ? THEN ?
                WHEN consultations.status = ? AND procedure_decisions.id IS NULL THEN ?
                WHEN visits.status = ? AND visit_check_ins.id IS NOT NULL AND consultations.id IS NULL THEN ?
                WHEN visits.status = ? AND consultation_bills.id IS NULL THEN ?
                WHEN visits.status = ? AND consultation_payments.id IS NULL THEN ?
                WHEN visits.status = ? AND consultation_clearances.id IS NULL THEN ?
                WHEN visits.status = ? THEN ?
                ELSE NULL
            END AS stage_key
            SQL;

        $stageBindings = [
            VisitStatus::Completed->value,
            OperationalVisitStage::Completed->value,
            RecoveryEpisodeStatus::ReadyForDischarge->value,
            OperationalVisitStage::ReadyForDischarge->value,
            RecoveryEpisodeStatus::InProgress->value,
            OperationalVisitStage::DoctorReviewRequired->value,
            RecoveryEpisodeStatus::InProgress->value,
            OperationalVisitStage::RecoveryInProgress->value,
            ProcedureRecordStatus::Completed->value,
            OperationalVisitStage::ReadyForNursingRecovery->value,
            ProcedureRecordStatus::InProgress->value,
            OperationalVisitStage::ProcedureInProgress->value,
            PreProcedureReadinessStatus::Ready->value,
            OperationalVisitStage::ReadyForDoctorProcedure->value,
            PreProcedureReadinessStatus::InPreparation->value,
            OperationalVisitStage::NursingPreparationInProgress->value,
            ProcedureDecisionOutcome::ProcedureRequired->value,
            OperationalVisitStage::AwaitingProcedureBilling->value,
            ProcedureDecisionOutcome::ProcedureRequired->value,
            OperationalVisitStage::AwaitingProcedurePayment->value,
            ProcedureDecisionOutcome::ProcedureRequired->value,
            OperationalVisitStage::AwaitingProcedureFinancialClearance->value,
            ProcedureDecisionOutcome::ProcedureRequired->value,
            OperationalVisitStage::ReadyForNursingPreparation->value,
            ConsultationStatus::InProgress->value,
            OperationalVisitStage::ConsultationInProgress->value,
            VisitStatus::CheckedIn->value,
            OperationalVisitStage::ReadyForDoctorConsultation->value,
            VisitStatus::Created->value,
            OperationalVisitStage::AwaitingConsultationBilling->value,
            VisitStatus::Created->value,
            OperationalVisitStage::AwaitingConsultationPayment->value,
            VisitStatus::Created->value,
            OperationalVisitStage::AwaitingConsultationFinancialClearance->value,
            VisitStatus::Created->value,
            OperationalVisitStage::ReadyForReceptionCheckIn->value,
        ];

        $counts = DB::table('visits')
            ->leftJoin('bills as consultation_bills', function (JoinClause $join): void {
                $join->on('consultation_bills.visit_id', '=', 'visits.id')
                    ->where('consultation_bills.type', BillType::Consultation->value);
            })
            ->leftJoin('payments as consultation_payments', 'consultation_payments.bill_id', '=', 'consultation_bills.id')
            ->leftJoin('financial_clearances as consultation_clearances', 'consultation_clearances.bill_id', '=', 'consultation_bills.id')
            ->leftJoin('visit_check_ins', 'visit_check_ins.visit_id', '=', 'visits.id')
            ->leftJoin('consultations', 'consultations.visit_id', '=', 'visits.id')
            ->leftJoin('procedure_decisions', 'procedure_decisions.visit_id', '=', 'visits.id')
            ->leftJoin('bills as procedure_bills', function (JoinClause $join): void {
                $join->on('procedure_bills.visit_id', '=', 'visits.id')
                    ->where('procedure_bills.type', BillType::Procedure->value);
            })
            ->leftJoin('payments as procedure_payments', 'procedure_payments.bill_id', '=', 'procedure_bills.id')
            ->leftJoin('receipts as procedure_receipts', 'procedure_receipts.payment_id', '=', 'procedure_payments.id')
            ->leftJoin('financial_clearances as procedure_clearances', 'procedure_clearances.bill_id', '=', 'procedure_bills.id')
            ->leftJoin('pre_procedure_readinesses', 'pre_procedure_readinesses.visit_id', '=', 'visits.id')
            ->leftJoin('procedure_records', 'procedure_records.visit_id', '=', 'visits.id')
            ->leftJoin('recovery_episodes', 'recovery_episodes.visit_id', '=', 'visits.id')
            ->leftJoin('recovery_escalations', function (JoinClause $join): void {
                $join->on('recovery_escalations.recovery_episode_id', '=', 'recovery_episodes.id')
                    ->where('recovery_escalations.open_marker', true);
            })
            ->whereBetween('visits.occurred_at', $period->bounds())
            ->selectRaw($stageProjectionSql, $stageBindings)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('stage_key')
            ->pluck('aggregate', 'stage_key');

        return array_map(
            static fn (OperationalVisitStage $stage): array => [
                'key' => $stage->value,
                'label' => $stage->displayName(),
                'count' => (int) ($counts[$stage->value] ?? 0),
            ],
            OperationalVisitStage::cases(),
        );
    }
}
