import { Form, Head } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index } from '@/routes/reports/clinical';
import type { ClinicalProcedureReport } from '@/types';

type ClinicalProcedureReportPageProps = {
    report: ClinicalProcedureReport;
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

export default function ClinicalProcedureReportPage({
    report,
}: ClinicalProcedureReportPageProps) {
    const {
        consultationDecision,
        escalation,
        period,
        preparation,
        procedure,
        procedureDistribution,
        recovery,
        terminalOutcomes,
    } = report;
    const hasData = [
        ...Object.values(consultationDecision),
        ...Object.values(procedure),
        ...Object.values(preparation),
        ...Object.values(recovery),
        ...Object.values(escalation),
        ...Object.values(terminalOutcomes),
    ].some((value) => value > 0);

    return (
        <>
            <Head title="Clinical / Procedure Report" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review aggregate clinical workflow milestones without patient records, narratives, staff rankings, or financial data."
                    eyebrow="Reporting"
                    title="Clinical / Procedure Report"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ errors, processing }) => (
                            <div className="grid gap-4 md:grid-cols-[minmax(12rem,1fr)_minmax(12rem,1fr)_auto] md:items-end">
                                <FormField
                                    error={errors.date_from}
                                    id="clinical-report-date-from"
                                    label="From date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.date_from
                                                ? 'clinical-report-date-from-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.date_from)}
                                        className={formControlStyles}
                                        defaultValue={period.fromDate}
                                        id="clinical-report-date-from"
                                        name="date_from"
                                        type="date"
                                    />
                                </FormField>
                                <FormField
                                    error={errors.date_to}
                                    id="clinical-report-date-to"
                                    label="Through date"
                                    required
                                >
                                    <input
                                        aria-describedby={
                                            errors.date_to
                                                ? 'clinical-report-date-to-error'
                                                : undefined
                                        }
                                        aria-invalid={Boolean(errors.date_to)}
                                        className={formControlStyles}
                                        defaultValue={period.throughDate}
                                        id="clinical-report-date-to"
                                        name="date_to"
                                        type="date"
                                    />
                                </FormField>
                                <div className="flex gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Generating...'
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
                    aria-label="Generated clinical report period"
                    className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm text-info"
                >
                    <p className="font-semibold">
                        {formatReportDate(period.fromDate)}–
                        {formatReportDate(period.throughDate)} ·{' '}
                        {period.timezone}
                    </p>
                    <p className="mt-1">
                        Each milestone uses its own recorded event time.
                        Procedure distribution labels reflect the current
                        catalog name; durable clinical relationships determine
                        the counts.
                    </p>
                </div>

                {!hasData ? (
                    <Panel>
                        <EmptyState
                            description="No structured clinical or procedure milestones were recorded in the selected period."
                            title="No clinical activity"
                        />
                    </Panel>
                ) : (
                    <>
                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Consultation and decisions
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Started consultations and immutable Doctor
                                    decisions recorded during the period.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-3">
                                <MetricCard
                                    label="Consultations started"
                                    value={
                                        consultationDecision.consultationsStarted
                                    }
                                />
                                <MetricCard
                                    label="Procedure required"
                                    value={
                                        consultationDecision.procedureRequired
                                    }
                                />
                                <MetricCard
                                    label="No procedure"
                                    value={consultationDecision.noProcedure}
                                />
                            </div>
                        </Panel>

                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Procedure activity
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Starts and completions are independent event
                                    cohorts; no potentially misleading
                                    completion percentage is calculated.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                <MetricCard
                                    label="Procedures started"
                                    value={procedure.started}
                                />
                                <MetricCard
                                    label="Procedures completed"
                                    value={procedure.completed}
                                />
                            </div>
                        </Panel>

                        <Panel className="overflow-hidden">
                            <header className="border-b border-border px-5 py-4">
                                <h2 className="text-lg font-semibold text-text">
                                    Procedure distribution
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Aggregate decision and completion counts by
                                    selected procedure service.
                                </p>
                            </header>
                            {procedureDistribution.length === 0 ? (
                                <EmptyState
                                    description="No procedure-required decisions or procedure completions were recorded in this period."
                                    title="No procedure distribution"
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full min-w-xl text-left text-sm">
                                        <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                            <tr>
                                                <th
                                                    className="px-5 py-4"
                                                    scope="col"
                                                >
                                                    Procedure
                                                </th>
                                                <th
                                                    className="px-5 py-4 text-right"
                                                    scope="col"
                                                >
                                                    Decisions
                                                </th>
                                                <th
                                                    className="px-5 py-4 text-right"
                                                    scope="col"
                                                >
                                                    Completed
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {procedureDistribution.map(
                                                (item, index) => (
                                                    <tr
                                                        key={`${item.procedureName}-${index}`}
                                                    >
                                                        <th
                                                            className="px-5 py-4 font-medium text-text"
                                                            scope="row"
                                                        >
                                                            {item.procedureName}
                                                        </th>
                                                        <td className="px-5 py-4 text-right text-text tabular-nums">
                                                            {item.procedureRequiredDecisions.toLocaleString()}
                                                        </td>
                                                        <td className="px-5 py-4 text-right font-semibold text-text tabular-nums">
                                                            {item.proceduresCompleted.toLocaleString()}
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Panel>

                        <Panel className="p-5">
                            <header>
                                <h2 className="text-lg font-semibold text-text">
                                    Preparation and recovery
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Structured Nursing workflow milestones only;
                                    observations, vitals, and notes are
                                    excluded.
                                </p>
                            </header>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                <MetricCard
                                    label="Preparations started"
                                    value={preparation.started}
                                />
                                <MetricCard
                                    label="Preparations completed"
                                    value={preparation.completed}
                                />
                                <MetricCard
                                    label="Recoveries started"
                                    value={recovery.started}
                                />
                                <MetricCard
                                    label="Recoveries completed"
                                    value={recovery.completed}
                                />
                                <MetricCard
                                    label="Discharges"
                                    value={recovery.discharged}
                                />
                            </div>
                        </Panel>

                        <div className="grid gap-4 lg:grid-cols-2">
                            <Panel className="p-5">
                                <h2 className="text-lg font-semibold text-text">
                                    Recovery escalations
                                </h2>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    <MetricCard
                                        label="Escalations raised"
                                        value={escalation.raised}
                                    />
                                    <MetricCard
                                        label="Escalations resolved"
                                        value={escalation.resolved}
                                    />
                                </div>
                            </Panel>
                            <Panel className="p-5">
                                <h2 className="text-lg font-semibold text-text">
                                    Terminal outcomes
                                </h2>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    <MetricCard
                                        label="Procedure-path Visits completed"
                                        value={
                                            terminalOutcomes.procedurePathVisitsCompleted
                                        }
                                    />
                                    <MetricCard
                                        label="No-procedure Visits completed"
                                        value={
                                            terminalOutcomes.noProcedureVisitsCompleted
                                        }
                                    />
                                </div>
                            </Panel>
                        </div>
                    </>
                )}
            </PageContainer>
        </>
    );
}

ClinicalProcedureReportPage.layout = [AuthenticatedLayout];
