<?php

namespace App\Actions\Reporting;

use App\Models\User;
use App\StaffPermission;
use Illuminate\Support\Facades\Gate;

final class BuildManagementSummary
{
    public function __construct(
        private AggregateOperationalMeasures $aggregateOperationalMeasures,
        private AggregateFinancialMeasures $aggregateFinancialMeasures,
    ) {}

    /**
     * @return array{
     *     period: array{fromDate: string, throughDate: string, timezone: string},
     *     visits: array{occurred: int, active: int, completed: int},
     *     clinical: array{consultationsStarted: int, procedureRequired: int, noProcedure: int, proceduresCompleted: int, recoveriesStarted: int, dischargesCompleted: int},
     *     financial: array{
     *         billedAmountMinor: int,
     *         paidAmountMinor: int,
     *         outstandingAmountMinor: int,
     *         consultation: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int},
     *         procedure: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int}
     *     }
     * }
     */
    public function handle(User $actor, ReportingPeriod $period): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::ReportsManagementView);

        $operationalMetrics = $this->aggregateOperationalMeasures->handle($period);
        $financialMetrics = $this->aggregateFinancialMeasures->handle($period);
        $timezone = config('app.timezone');

        return [
            'period' => [
                'fromDate' => $period->startsAt->toDateString(),
                'throughDate' => $period->endsAt->toDateString(),
                'timezone' => is_string($timezone) ? $timezone : 'UTC',
            ],
            'visits' => $operationalMetrics['visits'],
            'clinical' => $operationalMetrics['milestones'],
            'financial' => [
                'billedAmountMinor' => $financialMetrics['overall']['billedAmountMinor'],
                'paidAmountMinor' => $financialMetrics['overall']['paidAmountMinor'],
                'outstandingAmountMinor' => $financialMetrics['overall']['outstandingAmountMinor'],
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
