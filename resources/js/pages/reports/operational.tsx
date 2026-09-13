import { Form, Head } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index } from '@/routes/reports/operational';
import type { OperationalReport } from '@/types';

type OperationalReportPageProps = {
    report: OperationalReport;
};

const metricLabels = {
    active: 'Active Visits',
    completed: 'Completed Visits',
    consultationsStarted: 'Consultations started',
    dischargesCompleted: 'Discharges completed',
    noProcedure: 'No-procedure decisions',
    occurred: 'Total Visits',
    procedureRequired: 'Procedure-required decisions',
    proceduresCompleted: 'Procedures completed',
    recoveriesStarted: 'Recoveries started',
};

function formatReportDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

function MetricCard({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-control border border-border bg-surface-subtle px-4 py-4">
            <p className="text-sm font-medium text-text-secondary">{label}</p>
            <p className="mt-2 text-2xl font-semibold text-text tabular-nums">
                {value.toLocaleString()}
            </p>
        </div>
    );
}

export default function OperationalReportPage({
    report,
}: OperationalReportPageProps) {
    const { metrics, period, stages } = report;
    const activeStages = stages.filter((stage) => stage.count > 0);
    const hasData = [
        ...Object.values(metrics.visits),
        ...Object.values(metrics.milestones),
    ].some((value) => value > 0);

    return (
        <>
            <Head title="Operational Report" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review aggregate clinic flow and Visit workload without patient, financial, or clinical-detail exposure."
                    eyebrow="Reporting"
                    title="Operational Report"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ errors, processing }) => (
                            <div className="grid gap-4 md:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] md:items-end">
                                <FormField
                                    error={errors.date_from}
                                    id="operational-report-date-from"
                                    label="From date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.date_from
                                                ? 'operational-report-date-from-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.date_from)}
                                        className={formControlStyles}
                                        defaultValue={period.fromDate}
                                        id="operational-report-date-from"
                                        name="date_from"
                                        type="date"
                                    />
                                </FormField>
                                <FormField
                                    error={errors.date_to}
                                    id="operational-report-date-to"
                                    label="Through date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.date_to
                                                ? 'operational-report-date-to-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.date_to)}
                                        className={formControlStyles}
                                        defaultValue={period.throughDate}
                                        id="operational-report-date-to"
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
                    aria-label="Generated report period"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm text-info"
                >
                    <p className="font-semibold">
                        {formatReportDate(period.fromDate)}–
                        {formatReportDate(period.throughDate)}
                    </p>
                    <p>
                        Inclusive dates · {period.timezone} · Stages reflect the
                        current state of Visits occurring in this period
                    </p>
                </div>

                {!hasData ? (
                    <Panel>
                        <EmptyState
                            description="No Visits occurred in the selected period. Choose another date range to review clinic flow."
                            title="No operational data"
                        />
                    </Panel>
                ) : (
                    <>
                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Visit workload
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Visit occurrence and completion use their
                                    authoritative lifecycle timestamps.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <MetricCard
                                    label={metricLabels.occurred}
                                    value={metrics.visits.occurred}
                                />
                                <MetricCard
                                    label={metricLabels.active}
                                    value={metrics.visits.active}
                                />
                                <MetricCard
                                    label={metricLabels.completed}
                                    value={metrics.visits.completed}
                                />
                            </div>
                        </Panel>

                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Workflow milestones
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Counts use the recorded timestamp for each
                                    milestone within the selected period.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <MetricCard
                                    label={metricLabels.consultationsStarted}
                                    value={
                                        metrics.milestones.consultationsStarted
                                    }
                                />
                                <MetricCard
                                    label={metricLabels.procedureRequired}
                                    value={metrics.milestones.procedureRequired}
                                />
                                <MetricCard
                                    label={metricLabels.noProcedure}
                                    value={metrics.milestones.noProcedure}
                                />
                                <MetricCard
                                    label={metricLabels.proceduresCompleted}
                                    value={
                                        metrics.milestones.proceduresCompleted
                                    }
                                />
                                <MetricCard
                                    label={metricLabels.recoveriesStarted}
                                    value={metrics.milestones.recoveriesStarted}
                                />
                                <MetricCard
                                    label={metricLabels.dischargesCompleted}
                                    value={
                                        metrics.milestones.dischargesCompleted
                                    }
                                />
                            </div>
                        </Panel>

                        <Panel className="overflow-hidden">
                            <header className="border-b border-border px-5 py-4">
                                <h2 className="text-lg font-semibold text-text">
                                    Current operational stages
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Each Visit is counted once using durable
                                    workflow records, not presentation labels.
                                </p>
                            </header>
                            {activeStages.length === 0 ? (
                                <EmptyState
                                    description="No Visits occurred in this period, so there is no current-stage cohort to display."
                                    title="No Visit stages"
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-lg text-left text-sm">
                                        <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                            <tr>
                                                <th
                                                    className="px-5 py-4"
                                                    scope="col"
                                                >
                                                    Operational stage
                                                </th>
                                                <th
                                                    className="px-5 py-4 text-right"
                                                    scope="col"
                                                >
                                                    Visits
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {activeStages.map((stage) => (
                                                <tr key={stage.key}>
                                                    <th
                                                        className="px-5 py-4 font-medium text-text"
                                                        scope="row"
                                                    >
                                                        {stage.label}
                                                    </th>
                                                    <td className="px-5 py-4 text-right font-semibold text-text tabular-nums">
                                                        {stage.count.toLocaleString()}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                        <tfoot className="border-t border-border bg-surface-subtle">
                                            <tr>
                                                <th
                                                    className="px-5 py-4 font-semibold text-text"
                                                    scope="row"
                                                >
                                                    Total Visits
                                                </th>
                                                <td className="px-5 py-4 text-right font-semibold text-text tabular-nums">
                                                    {metrics.visits.occurred.toLocaleString()}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            )}
                        </Panel>
                    </>
                )}
            </PageContainer>
        </>
    );
}

OperationalReportPage.layout = [AuthenticatedLayout];
