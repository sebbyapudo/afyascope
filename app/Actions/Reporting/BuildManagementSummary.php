<?php

namespace App\Actions\Reporting;

use App\BillStatus;
use App\BillType;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\User;
use App\StaffPermission;
use Illuminate\Support\Facades\Gate;
use stdClass;

final class BuildManagementSummary
{
    public function __construct(private AggregateOperationalMeasures $aggregateOperationalMeasures) {}

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
        $billMetrics = $this->billMetrics($period);
        $paymentMetrics = $this->paymentMetrics($period);
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
                'billedAmountMinor' => $this->integer($billMetrics, 'billed'),
                'paidAmountMinor' => $this->integer($paymentMetrics, 'paid'),
                'outstandingAmountMinor' => $this->integer($billMetrics, 'outstanding'),
                'consultation' => [
                    'billedAmountMinor' => $this->integer($billMetrics, 'consultation_billed'),
                    'paidAmountMinor' => $this->integer($paymentMetrics, 'consultation_paid'),
                    'outstandingAmountMinor' => $this->integer($billMetrics, 'consultation_outstanding'),
                ],
                'procedure' => [
                    'billedAmountMinor' => $this->integer($billMetrics, 'procedure_billed'),
                    'paidAmountMinor' => $this->integer($paymentMetrics, 'procedure_paid'),
                    'outstandingAmountMinor' => $this->integer($billMetrics, 'procedure_outstanding'),
                ],
            ],
        ];
    }

    private function billMetrics(ReportingPeriod $period): ?stdClass
    {
        return BillItem::query()
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->whereBetween('bills.created_at', $period->bounds())
            ->toBase()
            ->selectRaw('COALESCE(SUM(bill_items.amount_minor), 0) as billed')
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.status = ? THEN bill_items.amount_minor ELSE 0 END), 0) as outstanding', [
                BillStatus::Open->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.type = ? THEN bill_items.amount_minor ELSE 0 END), 0) as consultation_billed', [
                BillType::Consultation->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.type = ? THEN bill_items.amount_minor ELSE 0 END), 0) as procedure_billed', [
                BillType::Procedure->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.type = ? AND bills.status = ? THEN bill_items.amount_minor ELSE 0 END), 0) as consultation_outstanding', [
                BillType::Consultation->value,
                BillStatus::Open->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.type = ? AND bills.status = ? THEN bill_items.amount_minor ELSE 0 END), 0) as procedure_outstanding', [
                BillType::Procedure->value,
                BillStatus::Open->value,
            ])
            ->first();
    }

    private function paymentMetrics(ReportingPeriod $period): ?stdClass
    {
        return Payment::query()
            ->join('bills', 'bills.id', '=', 'payments.bill_id')
            ->whereBetween('payments.recorded_at', $period->bounds())
            ->toBase()
            ->selectRaw('COALESCE(SUM(payments.amount_minor), 0) as paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.type = ? THEN payments.amount_minor ELSE 0 END), 0) as consultation_paid', [
                BillType::Consultation->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bills.type = ? THEN payments.amount_minor ELSE 0 END), 0) as procedure_paid', [
                BillType::Procedure->value,
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
