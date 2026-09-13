<?php

namespace App\Actions\Reporting;

use App\BillStatus;
use App\BillType;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AggregateFinancialMeasures
{
    /**
     * @return array{
     *     overall: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int, billCount: int, paidBillCount: int, outstandingBillCount: int},
     *     consultation: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int, billCount: int},
     *     procedure: array{billedAmountMinor: int, paidAmountMinor: int, outstandingAmountMinor: int, billCount: int},
     *     paymentCount: int
     * }
     */
    public function handle(ReportingPeriod $period): array
    {
        $billMetrics = $this->billMetrics($period);
        $paymentMetrics = $this->paymentMetrics($period);

        return [
            'overall' => [
                'billedAmountMinor' => $this->integer($billMetrics, 'billed'),
                'paidAmountMinor' => $this->integer($paymentMetrics, 'paid'),
                'outstandingAmountMinor' => $this->integer($billMetrics, 'outstanding'),
                'billCount' => $this->integer($billMetrics, 'bill_count'),
                'paidBillCount' => $this->integer($billMetrics, 'paid_bill_count'),
                'outstandingBillCount' => $this->integer($billMetrics, 'outstanding_bill_count'),
            ],
            'consultation' => [
                'billedAmountMinor' => $this->integer($billMetrics, 'consultation_billed'),
                'paidAmountMinor' => $this->integer($paymentMetrics, 'consultation_paid'),
                'outstandingAmountMinor' => $this->integer($billMetrics, 'consultation_outstanding'),
                'billCount' => $this->integer($billMetrics, 'consultation_bill_count'),
            ],
            'procedure' => [
                'billedAmountMinor' => $this->integer($billMetrics, 'procedure_billed'),
                'paidAmountMinor' => $this->integer($paymentMetrics, 'procedure_paid'),
                'outstandingAmountMinor' => $this->integer($billMetrics, 'procedure_outstanding'),
                'billCount' => $this->integer($billMetrics, 'procedure_bill_count'),
            ],
            'paymentCount' => $this->integer($paymentMetrics, 'payment_count'),
        ];
    }

    private function billMetrics(ReportingPeriod $period): ?stdClass
    {
        $billTotals = DB::table('bills')
            ->leftJoin('bill_items', 'bill_items.bill_id', '=', 'bills.id')
            ->select([
                'bills.id as bill_id',
                'bills.type',
                'bills.status',
                'bills.created_at as billed_at',
            ])
            ->selectRaw('COALESCE(SUM(bill_items.amount_minor), 0) as snapshot_amount_minor')
            ->groupBy('bills.id', 'bills.type', 'bills.status', 'bills.created_at');

        return DB::query()
            ->fromSub($billTotals, 'bill_totals')
            ->leftJoin('payments', 'payments.bill_id', '=', 'bill_totals.bill_id')
            ->whereBetween('bill_totals.billed_at', $period->bounds())
            ->selectRaw('COALESCE(SUM(bill_totals.snapshot_amount_minor), 0) as billed')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(payments.amount_minor, 0) >= bill_totals.snapshot_amount_minor THEN 0 ELSE bill_totals.snapshot_amount_minor - COALESCE(payments.amount_minor, 0) END), 0) as outstanding')
            ->selectRaw('COUNT(*) as bill_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.status = ? THEN 1 ELSE 0 END), 0) as paid_bill_count', [
                BillStatus::Paid->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(payments.amount_minor, 0) < bill_totals.snapshot_amount_minor THEN 1 ELSE 0 END), 0) as outstanding_bill_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.type = ? THEN bill_totals.snapshot_amount_minor ELSE 0 END), 0) as consultation_billed', [
                BillType::Consultation->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.type = ? THEN bill_totals.snapshot_amount_minor ELSE 0 END), 0) as procedure_billed', [
                BillType::Procedure->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.type = ? AND COALESCE(payments.amount_minor, 0) < bill_totals.snapshot_amount_minor THEN bill_totals.snapshot_amount_minor - COALESCE(payments.amount_minor, 0) ELSE 0 END), 0) as consultation_outstanding', [
                BillType::Consultation->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.type = ? AND COALESCE(payments.amount_minor, 0) < bill_totals.snapshot_amount_minor THEN bill_totals.snapshot_amount_minor - COALESCE(payments.amount_minor, 0) ELSE 0 END), 0) as procedure_outstanding', [
                BillType::Procedure->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.type = ? THEN 1 ELSE 0 END), 0) as consultation_bill_count', [
                BillType::Consultation->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN bill_totals.type = ? THEN 1 ELSE 0 END), 0) as procedure_bill_count', [
                BillType::Procedure->value,
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
            ->selectRaw('COUNT(*) as payment_count')
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
