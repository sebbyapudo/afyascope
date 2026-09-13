<?php

namespace App\Actions\Reporting;

use App\Models\User;
use App\StaffPermission;
use Illuminate\Support\Facades\Gate;

final class BuildManagementSummary
{
    public function __construct(
        private AggregateVisitMeasures $aggregateVisitMeasures,
        private AggregateFinancialMeasures $aggregateFinancialMeasures,
        private AggregateClinicalProcedureMeasures $aggregateClinicalProcedureMeasures,
    ) {}

    /**
     * @return array{
     *     period: array{fromDate: string, throughDate: string, timezone: string},
     *     currency: string,
     *     visits: array{occurred: int, active: int, completed: int},
     *     clinical: array{consultationsStarted: int, procedureRequired: int, noProcedure: int, proceduresStarted: int, proceduresCompleted: int, recoveriesStarted: int, recoveriesCompleted: int, recoveryEscalationsRaised: int, dischargesCompleted: int, procedurePathVisitsCompleted: int, noProcedureVisitsCompleted: int},
     *     financial: array{
     *         billedAmountMinor: int,
     *         paidAmountMinor: int,
     *         outstandingAmountMinor: int,
     *         billCount: int,
     *         paymentCount: int,
     *         consultation: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int},
     *         procedure: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int}
     *     }
     * }
     */
    public function handle(User $actor, ReportingPeriod $period): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::ReportsManagementView);

        $visitMetrics = $this->aggregateVisitMeasures->handle($period);
        $financialMetrics = $this->aggregateFinancialMeasures->handle($period);
        $clinicalMetrics = $this->aggregateClinicalProcedureMeasures->handle($period);
        $timezone = config('app.timezone');

        return [
            'period' => [
                'fromDate' => $period->startsAt->toDateString(),
                'throughDate' => $period->endsAt->toDateString(),
                'timezone' => is_string($timezone) ? $timezone : 'UTC',
            ],
            'currency' => 'KES',
            'visits' => $visitMetrics,
            'clinical' => [
                'consultationsStarted' => $clinicalMetrics['consultationDecision']['consultationsStarted'],
                'procedureRequired' => $clinicalMetrics['consultationDecision']['procedureRequired'],
                'noProcedure' => $clinicalMetrics['consultationDecision']['noProcedure'],
                'proceduresStarted' => $clinicalMetrics['procedure']['started'],
                'proceduresCompleted' => $clinicalMetrics['procedure']['completed'],
                'recoveriesStarted' => $clinicalMetrics['recovery']['started'],
                'recoveriesCompleted' => $clinicalMetrics['recovery']['completed'],
                'recoveryEscalationsRaised' => $clinicalMetrics['escalation']['raised'],
                'dischargesCompleted' => $clinicalMetrics['recovery']['discharged'],
                'procedurePathVisitsCompleted' => $clinicalMetrics['terminalOutcomes']['procedurePathVisitsCompleted'],
                'noProcedureVisitsCompleted' => $clinicalMetrics['terminalOutcomes']['noProcedureVisitsCompleted'],
            ],
            'financial' => [
                'billedAmountMinor' => $financialMetrics['overall']['billedAmountMinor'],
                'paidAmountMinor' => $financialMetrics['overall']['paidAmountMinor'],
                'outstandingAmountMinor' => $financialMetrics['overall']['outstandingAmountMinor'],
                'billCount' => $financialMetrics['overall']['billCount'],
                'paymentCount' => $financialMetrics['paymentCount'],
                'consultation' => [
                    'billedAmountMinor' => $financialMetrics['consultation']['billedAmountMinor'],
                    'paidAmountMinor' => $financialMetrics['consultation']['paidAmountMinor'],
                    'outstandingAmountMinor' => $financialMetrics['consultation']['outstandingAmountMinor'],
                ],
                'procedure' => [
                    'billedAmountMinor' => $financialMetrics['procedure']['billedAmountMinor'],
                    'paidAmountMinor' => $financialMetrics['procedure']['paidAmountMinor'],
                    'outstandingAmountMinor' => $financialMetrics['procedure']['outstandingAmountMinor'],
                ],
            ],
        ];
    }
}
