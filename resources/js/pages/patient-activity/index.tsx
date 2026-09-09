import { Form, Head, Link } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as appointmentShow } from '@/routes/appointments';
import { show as billShow } from '@/routes/billing/bills';
import { show as clearanceShow } from '@/routes/billing/clearances';
import { show as receiptShow } from '@/routes/billing/receipts';
import { show as checkInShow } from '@/routes/check-ins';
import { show as consultationShow } from '@/routes/clinical/consultations';
import { show as procedureShow } from '@/routes/clinical/procedures';
import { show as preparationShow } from '@/routes/nursing/pre-procedure-readiness';
import { show as recoveryShow } from '@/routes/nursing/recovery';
import { index } from '@/routes/patient-activity';
import { show as visitShow } from '@/routes/visits';
import type {
    PatientActivityDestination,
    PatientActivityOption,
    PatientActivityPage,
} from '@/types';

type PatientActivityIndexProps = {
    activities: PatientActivityPage;
    filters: {
        activity: string;
        q: string;
    };
    activityOptions: PatientActivityOption[];
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

function destinationRoute(destination: PatientActivityDestination) {
    switch (destination.type) {
        case 'appointment':
            return appointmentShow(destination.id);
        case 'bill':
            return billShow(destination.id);
        case 'check_in':
            return checkInShow(destination.id);
        case 'clearance':
            return clearanceShow(destination.id);
        case 'consultation':
            return consultationShow(destination.id);
        case 'preparation':
            return preparationShow(destination.id);
        case 'procedure':
            return procedureShow(destination.id);
        case 'receipt':
            return receiptShow(destination.id);
        case 'recovery':
            return recoveryShow(destination.id);
        case 'visit':
            return visitShow(destination.id);
    }
}

export default function PatientActivityIndex({
    activities,
    filters,
    activityOptions,
}: PatientActivityIndexProps) {
    const { data, pagination } = activities;
    const hasFilters = Boolean(filters.q || filters.activity);

    return (
        <>
            <Head title="Patient tracking" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review the Visits you previously handled and their current AfyaScope workflow stage. Operational queues remain limited to work needing action now."
                    title="Patient tracking"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ processing }) => (
                            <div className="grid gap-4 lg:grid-cols-[minmax(18rem,1fr)_16rem_auto] lg:items-end">
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="patient-activity-search"
                                    >
                                        Search Patient activity
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.q}
                                        id="patient-activity-search"
                                        maxLength={100}
                                        name="q"
                                        placeholder="Patient or Visit reference, or Patient name"
                                        type="search"
                                    />
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="patient-activity-filter"
                                    >
                                        Activity
                                    </label>
                                    <select
                                        className={formControlStyles}
                                        defaultValue={filters.activity}
                                        id="patient-activity-filter"
                                        name="activity"
                                    >
                                        <option value="">
                                            All my activity
                                        </option>
                                        {activityOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing ? 'Filtering…' : 'Filter'}
                                    </Button>
                                    {hasFilters ? (
                                        <ActionLink
                                            href={index()}
                                            variant="secondary"
                                        >
                                            Clear
                                        </ActionLink>
                                    ) : null}
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <Panel className="overflow-hidden">
                    <header className="border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">
                            Previously handled Visits
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Newest relevant activity appears first. These rows
                            are history, not a waiting queue.
                        </p>
                    </header>

                    {data.length === 0 ? (
                        <EmptyState
                            action={
                                hasFilters ? (
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear filters
                                    </ActionLink>
                                ) : null
                            }
                            description={
                                hasFilters
                                    ? 'No activity attributed to you matches these filters.'
                                    : 'Visits will appear after you complete an attributed workflow action.'
                            }
                            title={
                                hasFilters
                                    ? 'No matching activity'
                                    : 'No Patient activity yet'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Visit
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            My latest activity
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Current stage
                                        </th>
                                        <th
                                            className="px-5 py-4 text-right"
                                            scope="col"
                                        >
                                            Record
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.map((item) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={item.visit.visitNumber}
                                        >
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {item.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {item.patient.patientNumber}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {item.visit.visitNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {item.activity.label}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {item.activity.reference} ·{' '}
                                                    {formatDateTime(
                                                        item.activity
                                                            .occurredAt,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="info">
                                                    {item.visit.currentStage}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={destinationRoute(
                                                        item.destination,
                                                    )}
                                                >
                                                    View record
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {pagination.from}–{pagination.to} of{' '}
                                {pagination.total}
                            </p>
                            <nav
                                aria-label="Patient activity pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                activity:
                                                    filters.activity ||
                                                    undefined,
                                                activity_page:
                                                    pagination.currentPage - 1,
                                                q: filters.q || undefined,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {pagination.currentPage <
                                pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                activity:
                                                    filters.activity ||
                                                    undefined,
                                                activity_page:
                                                    pagination.currentPage + 1,
                                                q: filters.q || undefined,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Next
                                    </ActionLink>
                                ) : null}
                            </nav>
                        </footer>
                    ) : null}
                </Panel>
            </PageContainer>
        </>
    );
}

PatientActivityIndex.layout = [AuthenticatedLayout];
