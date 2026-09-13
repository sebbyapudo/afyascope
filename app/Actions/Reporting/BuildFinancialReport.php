<?php

namespace App\Actions\Reporting;

use App\Models\FinancialClearance;
use App\Models\Receipt;
use App\Models\User;
use App\StaffPermission;
use Illuminate\Support\Facades\Gate;

final class BuildFinancialReport
{
    public function __construct(private AggregateFinancialMeasures $aggregateFinancialMeasures) {}

    /**
     * @return array{
     *     period: array{fromDate: string, throughDate: string, timezone: string},
     *     currency: string,
     *     overall: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int, billCount: int, paidBillCount: int, outstandingBillCount: int},
     *     consultation: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int, billCount: int},
     *     procedure: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int, billCount: int},
     *     flow: array{paymentCount: int, receiptCount: int, financialClearanceCount: int}
     * }
     */
    public function handle(User $actor, ReportingPeriod $period): array
    {
        Gate::forUser($actor)->authorize(StaffPermission::ReportsFinancialView);
        $measures = $this->aggregateFinancialMeasures->handle($period);
        $timezone = config('app.timezone');

        return [
            'period' => [
                'fromDate' => $period->startsAt->toDateString(),
                'throughDate' => $period->endsAt->toDateString(),
                'timezone' => is_string($timezone) ? $timezone : 'UTC',
            ],
            'currency' => 'KES',
            'overall' => $measures['overall'],
            'consultation' => $measures['consultation'],
            'procedure' => $measures['procedure'],
            'flow' => [
                'paymentCount' => $measures['paymentCount'],
                'receiptCount' => Receipt::query()
                    ->whereBetween('issued_at', $period->bounds())
                    ->count(),
                'financialClearanceCount' => FinancialClearance::query()
                    ->whereBetween('granted_at', $period->bounds())
                    ->count(),
            ],
        ];
    }
}
