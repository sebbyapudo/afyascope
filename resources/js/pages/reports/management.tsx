import { Form, Head } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index as clinicalReportIndex } from '@/routes/reports/clinical';
import { index as financialReportIndex } from '@/routes/reports/financial';
import { index as managementSummaryIndex } from '@/routes/reports/management';
import { index as operationalReportIndex } from '@/routes/reports/operational';
import type { ManagementSummary } from '@/types';

type ManagementSummaryPageProps = {
    summary: ManagementSummary;
};

function formatReportDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
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

function MoneyMetric({
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

export default function ManagementSummaryPage({
    summary,
}: ManagementSummaryPageProps) {
    const { clinical, currency, financial, period, visits } = summary;
    const hasData =
        Object.values(visits).some((value) => value > 0) ||
        Object.values(clinical).some((value) => value > 0) ||
        financial.billCount > 0 ||
        financial.paymentCount > 0;
    const detailedReportQuery = {
        date_from: period.fromDate,
        date_to: period.throughDate,
    };

    return (
        <>
            <Head title="Management Summary" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review a concise, aggregate view of clinic operations, financial activity, and structured clinical workflow."
                    eyebrow="Management reporting"
                    title="Management Summary"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={managementSummaryIndex()}>
                        {({ errors, processing }) => (
                            <div className="grid gap-4 md:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] md:items-end">
                                <FormField
                                    error={errors.from}
                                    id="management-summary-from"
                                    label="From date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.from
                                                ? 'management-summary-from-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.from)}
                                        className={formControlStyles}
                                        defaultValue={period.fromDate}
                                        id="management-summary-from"
                                        name="from"
                                        type="date"
                                    />
                                </FormField>
                                <FormField
                                    error={errors.to}
                                    id="management-summary-to"
                                    label="Through date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.to
                                                ? 'management-summary-to-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.to)}
                                        className={formControlStyles}
                                        defaultValue={period.throughDate}
                                        id="management-summary-to"
                                        name="to"
                                        type="date"
                                    />
                                </FormField>
                                <div className="flex flex-wrap gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Generating…'
                                            : 'Generate'}
                                    </Button>
                                    <ActionLink
                                        href={managementSummaryIndex()}
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
                    aria-label="Generated management summary period"
                    className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm text-info"
                >
                    <p className="font-semibold">
                        {formatReportDate(period.fromDate)}–
                        {formatReportDate(period.throughDate)} ·{' '}
                        {period.timezone}
                    </p>
                    <p className="mt-1">
                        Each measure uses its authoritative lifecycle event
                        date. Outstanding is the current balance of Bills
                        created in this period.
                    </p>
                </div>

                {!hasData ? (
                    <Panel>
                        <EmptyState
                            description="No operational, financial, or structured clinical activity was recorded for this period."
                            title="No management summary data"
                        />
                    </Panel>
                ) : (
                    <>
                        <Panel className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <header>
                                    <h2 className="text-lg font-semibold text-text">
                                        Operations
                                    </h2>
                                    <p className="mt-1 text-sm text-text-secondary">
                                        Visit workload and consultation decision
                                        activity.
                                    </p>
                                </header>
                                <ActionLink
                                    href={operationalReportIndex({
                                        query: detailedReportQuery,
                                    })}
                                    variant="secondary"
                                >
                                    View Operational Report
                                </ActionLink>
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <CountMetric
                                    label="Total Visits"
                                    value={visits.occurred}
                                />
                                <CountMetric
                                    label="Active Visits"
                                    value={visits.active}
                                />
                                <CountMetric
                                    label="Completed Visits"
                                    value={visits.completed}
                                />
                                <CountMetric
                                    label="Consultations started"
                                    value={clinical.consultationsStarted}
                                />
                                <CountMetric
                                    label="Procedure-required decisions"
                                    value={clinical.procedureRequired}
                                />
                                <CountMetric
                                    label="No-procedure decisions"
                                    value={clinical.noProcedure}
                                />
                            </div>
                        </Panel>

                        <Panel className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <header>
                                    <h2 className="text-lg font-semibold text-text">
                                        Finance
                                    </h2>
                                    <p className="mt-1 text-sm text-text-secondary">
                                        Immutable Bill snapshots, Payment
                                        activity, and current outstanding
                                        balances.
                                    </p>
                                </header>
                                <ActionLink
                                    href={financialReportIndex({
                                        query: detailedReportQuery,
                                    })}
                                    variant="secondary"
                                >
                                    View Financial Report
                                </ActionLink>
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <MoneyMetric
                                    amountMinor={financial.billedAmountMinor}
                                    currency={currency}
                                    label="Billed"
                                />
                                <MoneyMetric
                                    amountMinor={financial.paidAmountMinor}
                                    currency={currency}
                                    label="Payment activity"
                                />
                                <MoneyMetric
                                    amountMinor={
                                        financial.outstandingAmountMinor
                                    }
                                    currency={currency}
                                    label="Current outstanding"
                                />
                                <CountMetric
                                    label="Bills created"
                                    value={financial.billCount}
                                />
                                <CountMetric
                                    label="Payments recorded"
                                    value={financial.paymentCount}
                                />
                            </div>
                        </Panel>

                        <Panel className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <header>
                                    <h2 className="text-lg font-semibold text-text">
                                        Clinical / procedure activity
                                    </h2>
                                    <p className="mt-1 text-sm text-text-secondary">
                                        Structured aggregate milestones only; no
                                        patient or clinical narratives.
                                    </p>
                                </header>
                                <ActionLink
                                    href={clinicalReportIndex({
                                        query: detailedReportQuery,
                                    })}
                                    variant="secondary"
                                >
                                    View Clinical / Procedure Report
                                </ActionLink>
                            </div>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <CountMetric
                                    label="Procedures started"
                                    value={clinical.proceduresStarted}
                                />
                                <CountMetric
                                    label="Procedures completed"
                                    value={clinical.proceduresCompleted}
                                />
                                <CountMetric
                                    label="Recoveries started"
                                    value={clinical.recoveriesStarted}
                                />
                                <CountMetric
                                    label="Recoveries completed"
                                    value={clinical.recoveriesCompleted}
                                />
                                <CountMetric
                                    label="Recovery escalations raised"
                                    value={clinical.recoveryEscalationsRaised}
                                />
                                <CountMetric
                                    label="Discharges"
                                    value={clinical.dischargesCompleted}
                                />
                                <CountMetric
                                    label="Completed procedure-path Visits"
                                    value={
                                        clinical.procedurePathVisitsCompleted
                                    }
                                />
                                <CountMetric
                                    label="Completed no-procedure Visits"
                                    value={clinical.noProcedureVisitsCompleted}
                                />
                            </div>
                        </Panel>
                    </>
                )}
            </PageContainer>
        </>
    );
}

ManagementSummaryPage.layout = [AuthenticatedLayout];
