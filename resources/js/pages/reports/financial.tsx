import { Form, Head } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index } from '@/routes/reports/financial';
import type { FinancialReport } from '@/types';

type FinancialReportPageProps = {
    report: FinancialReport;
};

function formatReportDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

function MoneyMetricCard({
    amountMinor,
    currency,
    label,
}: {
    amountMinor: number;
    currency: string;
    label: string;
}) {
    return (
        <div className="rounded-control border border-border bg-surface-subtle px-4 py-4">
            <p className="text-sm font-medium text-text-secondary">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-text tabular-nums">
                <span className="mr-1 text-sm font-medium text-text-secondary">
                    {currency}
                </span>
                {formatMinorAmount(amountMinor)}
            </p>
        </div>
    );
}

function CountMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-control border border-border bg-surface-subtle px-4 py-4">
            <p className="text-sm font-medium text-text-secondary">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-text tabular-nums">
                {value.toLocaleString()}
            </p>
        </div>
    );
}

export default function FinancialReportPage({
    report,
}: FinancialReportPageProps) {
    const { consultation, currency, flow, overall, period, procedure } = report;
    const hasData =
        overall.billCount > 0 ||
        flow.paymentCount > 0 ||
        flow.receiptCount > 0 ||
        flow.financialClearanceCount > 0;
    const summaries = [
        { label: 'Consultation', summary: consultation },
        { label: 'Procedure', summary: procedure },
    ];

    return (
        <>
            <Head title="Financial Report" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review aggregate billing and financial-flow activity from immutable transaction records."
                    eyebrow="Reporting"
                    title="Financial Report"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ errors, processing }) => (
                            <div className="grid gap-4 md:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] md:items-end">
                                <FormField
                                    error={errors.date_from}
                                    id="financial-report-date-from"
                                    label="From date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.date_from
                                                ? 'financial-report-date-from-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.date_from)}
                                        className={formControlStyles}
                                        defaultValue={period.fromDate}
                                        id="financial-report-date-from"
                                        name="date_from"
                                        type="date"
                                    />
                                </FormField>
                                <FormField
                                    error={errors.date_to}
                                    id="financial-report-date-to"
                                    label="Through date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.date_to
                                                ? 'financial-report-date-to-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.date_to)}
                                        className={formControlStyles}
                                        defaultValue={period.throughDate}
                                        id="financial-report-date-to"
                                        name="date_to"
                                        type="date"
                                    />
                                </FormField>
                                <div className="flex gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Generating…'
                                            : 'Generate'}
                                    </Button>
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Current month
                                    </ActionLink>
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <div
                    aria-label="Generated financial report period"
                    className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm text-info"
                >
                    <p className="font-semibold">
                        {formatReportDate(period.fromDate)}–
                        {formatReportDate(period.throughDate)} ·{' '}
                        {period.timezone}
                    </p>
                    <p className="mt-1">
                        Bills use creation date; Payments, Receipts, and
                        Clearances use their own event dates. Outstanding is the
                        current balance of Bills created in this period.
                    </p>
                </div>

                {!hasData ? (
                    <Panel>
                        <EmptyState
                            description="No Bill or financial-flow activity was recorded in the selected period."
                            title="No financial data"
                        />
                    </Panel>
                ) : (
                    <>
                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Financial position
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Billed and outstanding values use immutable
                                    Bill item snapshots. Paid is Payment
                                    activity recorded during the selected
                                    period.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <MoneyMetricCard
                                    amountMinor={overall.billedAmountMinor}
                                    currency={currency}
                                    label="Total billed"
                                />
                                <MoneyMetricCard
                                    amountMinor={overall.paidAmountMinor}
                                    currency={currency}
                                    label="Total paid"
                                />
                                <MoneyMetricCard
                                    amountMinor={overall.outstandingAmountMinor}
                                    currency={currency}
                                    label="Current outstanding"
                                />
                            </div>
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                <CountMetric
                                    label="Bills created"
                                    value={overall.billCount}
                                />
                                <CountMetric
                                    label="Paid Bills"
                                    value={overall.paidBillCount}
                                />
                                <CountMetric
                                    label="Bills outstanding"
                                    value={overall.outstandingBillCount}
                                />
                            </div>
                        </Panel>

                        <Panel className="overflow-hidden">
                            <header className="border-b border-border px-5 py-4">
                                <h2 className="text-lg font-semibold text-text">
                                    Billing-gate breakdown
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Consultation and procedure financial gates
                                    remain separate.
                                </p>
                            </header>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-3xl text-left text-sm">
                                    <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        <tr>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Billing gate
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Bills
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Billed ({currency})
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Paid ({currency})
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Outstanding ({currency})
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {summaries.map(({ label, summary }) => (
                                            <tr key={label}>
                                                <th
                                                    className="px-5 py-4 font-medium text-text"
                                                    scope="row"
                                                >
                                                    {label}
                                                </th>
                                                <td className="px-5 py-4 text-right text-text tabular-nums">
                                                    {summary.billCount.toLocaleString()}
                                                </td>
                                                <td className="px-5 py-4 text-right text-text tabular-nums">
                                                    {formatMinorAmount(
                                                        summary.billedAmountMinor,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-right text-text tabular-nums">
                                                    {formatMinorAmount(
                                                        summary.paidAmountMinor,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-right font-semibold text-text tabular-nums">
                                                    {formatMinorAmount(
                                                        summary.outstandingAmountMinor,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Panel>

                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Financial flow
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Counts come from the independently persisted
                                    financial events recorded in this period.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <CountMetric
                                    label="Payments recorded"
                                    value={flow.paymentCount}
                                />
                                <CountMetric
                                    label="Receipts issued"
                                    value={flow.receiptCount}
                                />
                                <CountMetric
                                    label="Financial clearances"
                                    value={flow.financialClearanceCount}
                                />
                            </div>
                        </Panel>
                    </>
                )}
            </PageContainer>
        </>
    );
}

FinancialReportPage.layout = [AuthenticatedLayout];
