import { Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as showAppointment } from '@/routes/appointments';
import { edit, index } from '@/routes/patients';
import { create as createAppointment } from '@/routes/patients/appointments';
import { create as createVisit } from '@/routes/patients/visits';
import { show as showVisit } from '@/routes/visits';
import type {
    AppointmentStatus,
    PatientAppointmentHistoryItem,
    PatientDetails,
    PatientHistoryPage,
    PatientVisitHistoryItem,
} from '@/types';

type ShowPatientProps = {
    appointmentHistory: PatientHistoryPage<PatientAppointmentHistoryItem> | null;
    patient: PatientDetails;
    pastUnresolvedAppointments: PatientHistoryPage<PatientAppointmentHistoryItem> | null;
    status?: string | null;
    upcomingAppointments: PatientHistoryPage<PatientAppointmentHistoryItem> | null;
    visitHistory: PatientHistoryPage<PatientVisitHistoryItem> | null;
};

type HistoryPaginationProps = {
    label: string;
    pagination: PatientHistoryPage<unknown>['pagination'];
};

function formatDate(date: string | null): string {
    if (!date) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(
        new Date(`${date}T00:00:00`),
    );
}

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

function appointmentStatusTone(status: AppointmentStatus['value']) {
    if (status === 'cancelled') {
        return 'danger' as const;
    }

    if (status === 'no_show') {
        return 'warning' as const;
    }

    return 'info' as const;
}

function HistoryPagination({ label, pagination }: HistoryPaginationProps) {
    if (pagination.total === 0) {
        return null;
    }

    return (
        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
            <p className="tabular-nums">
                Page {pagination.currentPage} of {pagination.lastPage} · Showing{' '}
                {pagination.from}–{pagination.to} of {pagination.total}
            </p>
            {pagination.lastPage > 1 ? (
                <nav aria-label={label} className="flex items-center gap-2">
                    {pagination.previousPageUrl ? (
                        <ActionLink
                            href={pagination.previousPageUrl}
                            size="small"
                            variant="secondary"
                        >
                            Previous
                        </ActionLink>
                    ) : null}
                    {pagination.nextPageUrl ? (
                        <ActionLink
                            href={pagination.nextPageUrl}
                            size="small"
                            variant="secondary"
                        >
                            Next
                        </ActionLink>
                    ) : null}
                </nav>
            ) : null}
        </footer>
    );
}

export default function ShowPatient({
    appointmentHistory,
    patient,
    pastUnresolvedAppointments,
    status,
    upcomingAppointments,
    visitHistory,
}: ShowPatientProps) {
    const { props } = usePage();
    const fields = [
        ['Patient reference', patient.patientNumber],
        ['Full name', patient.name],
        ['Date of birth', formatDate(patient.dateOfBirth)],
        ['Sex', patient.sex?.label ?? 'Not recorded'],
        ['Phone number', patient.phone ?? 'Not recorded'],
        ['Email address', patient.email ?? 'Not recorded'],
        ['Address', patient.address ?? 'Not recorded'],
        [
            'Registered at',
            patient.createdAt
                ? formatDateTime(patient.createdAt)
                : 'Not recorded',
        ],
    ];

    return (
        <>
            <Head title={patient.patientNumber} />
            <PageContainer width="wide">
                <PageHeader
                    actions={
                        <div className="flex flex-wrap gap-3">
                            {props.auth.capabilities.createVisits ? (
                                <ActionLink href={createVisit(patient.id)}>
                                    Start new Visit
                                </ActionLink>
                            ) : null}
                            {props.auth.capabilities.createAppointments ? (
                                <ActionLink
                                    href={createAppointment(patient.id)}
                                    variant="secondary"
                                >
                                    Schedule appointment
                                </ActionLink>
                            ) : null}
                            {props.auth.capabilities.updatePatients ? (
                                <ActionLink
                                    href={edit(patient.id)}
                                    variant="secondary"
                                >
                                    Edit demographics
                                </ActionLink>
                            ) : null}
                        </div>
                    }
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to Patient registry
                        </Link>
                    }
                    description="Demographics, contact information, Visits, and appointment history"
                    eyebrow={patient.patientNumber}
                    title={patient.name}
                />

                {status ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {status}
                    </p>
                ) : null}

                <Panel className="p-5 sm:p-8">
                    <h2 className="text-lg font-semibold text-text">
                        Administrative profile
                    </h2>
                    <p className="mt-1 text-sm text-text-secondary">
                        Current registration, demographic, and contact details.
                    </p>
                    <dl className="mt-6 grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-4">
                        {fields.map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    {label}
                                </dt>
                                <dd className="mt-2 text-sm leading-6 whitespace-pre-line text-text">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Panel>

                {props.auth.capabilities.viewVisits && visitHistory ? (
                    <Panel
                        className="scroll-mt-6 overflow-hidden"
                        id="visit-history"
                    >
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="text-lg font-semibold text-text">
                                Visit history
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Attendance episodes ordered from newest to
                                oldest.
                            </p>
                        </div>
                        {visitHistory.data.length === 0 ? (
                            <EmptyState
                                action={
                                    props.auth.capabilities.createVisits ? (
                                        <ActionLink
                                            href={createVisit(patient.id)}
                                        >
                                            Start new Visit
                                        </ActionLink>
                                    ) : null
                                }
                                description="A Visit records an actual attendance at the clinic."
                                title="No Visits recorded"
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-4xl text-left text-sm">
                                    <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        <tr>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Visit
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Occurred
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Status
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Current workflow
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {visitHistory.data.map((visit) => (
                                            <tr key={visit.id}>
                                                <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                    {visit.visitNumber}
                                                </td>
                                                <td className="px-5 py-4 text-text-secondary">
                                                    <time
                                                        dateTime={
                                                            visit.occurredAt
                                                        }
                                                    >
                                                        {formatDateTime(
                                                            visit.occurredAt,
                                                        )}
                                                    </time>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <StatusBadge tone="info">
                                                        {visit.status.label}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-5 py-4 text-text-secondary">
                                                    {visit.nextStep}
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    <Link
                                                        className={
                                                            textLinkStyles
                                                        }
                                                        href={showVisit(
                                                            visit.id,
                                                        )}
                                                    >
                                                        View Visit
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <HistoryPagination
                            label="Patient Visit history pagination"
                            pagination={visitHistory.pagination}
                        />
                    </Panel>
                ) : null}

                {props.auth.capabilities.viewAppointments &&
                upcomingAppointments ? (
                    <Panel
                        className="scroll-mt-6 overflow-hidden"
                        id="upcoming-appointments"
                    >
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="text-lg font-semibold text-text">
                                Upcoming appointments
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Future scheduled attendances ordered by the
                                nearest date.
                            </p>
                        </div>
                        {upcomingAppointments.data.length === 0 ? (
                            <EmptyState
                                action={
                                    props.auth.capabilities
                                        .createAppointments ? (
                                        <ActionLink
                                            href={createAppointment(patient.id)}
                                        >
                                            Schedule appointment
                                        </ActionLink>
                                    ) : null
                                }
                                description="Appointments are scheduling records and do not create Visits."
                                title="No upcoming appointments"
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-3xl text-left text-sm">
                                    <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        <tr>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Appointment
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Scheduled
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Status
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {upcomingAppointments.data.map(
                                            (appointment) => (
                                                <tr key={appointment.id}>
                                                    <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                        {
                                                            appointment.appointmentNumber
                                                        }
                                                    </td>
                                                    <td className="px-5 py-4 text-text-secondary">
                                                        <time
                                                            dateTime={
                                                                appointment.scheduledAt
                                                            }
                                                        >
                                                            {formatDateTime(
                                                                appointment.scheduledAt,
                                                            )}
                                                        </time>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <StatusBadge tone="info">
                                                            {
                                                                appointment
                                                                    .status
                                                                    .label
                                                            }
                                                        </StatusBadge>
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        <Link
                                                            className={
                                                                textLinkStyles
                                                            }
                                                            href={showAppointment(
                                                                appointment.id,
                                                            )}
                                                        >
                                                            View appointment
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <HistoryPagination
                            label="Upcoming appointment pagination"
                            pagination={upcomingAppointments.pagination}
                        />
                    </Panel>
                ) : null}

                {props.auth.capabilities.viewAppointments &&
                pastUnresolvedAppointments ? (
                    <Panel
                        className="scroll-mt-6 overflow-hidden"
                        id="past-unresolved-appointments"
                    >
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="text-lg font-semibold text-text">
                                Past unresolved appointments
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Past scheduled appointments that have not been
                                cancelled or marked as no-show, ordered from the
                                most recent.
                            </p>
                        </div>
                        {pastUnresolvedAppointments.data.length === 0 ? (
                            <EmptyState
                                description="Past scheduled appointments requiring administrative review will appear here."
                                title="No past unresolved appointments"
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-3xl text-left text-sm">
                                    <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        <tr>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Appointment
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Scheduled for
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Status
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {pastUnresolvedAppointments.data.map(
                                            (appointment) => (
                                                <tr key={appointment.id}>
                                                    <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                        {
                                                            appointment.appointmentNumber
                                                        }
                                                    </td>
                                                    <td className="px-5 py-4 text-text-secondary">
                                                        <time
                                                            dateTime={
                                                                appointment.scheduledAt
                                                            }
                                                        >
                                                            {formatDateTime(
                                                                appointment.scheduledAt,
                                                            )}
                                                        </time>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <StatusBadge tone="info">
                                                            {
                                                                appointment
                                                                    .status
                                                                    .label
                                                            }
                                                        </StatusBadge>
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        <Link
                                                            className={
                                                                textLinkStyles
                                                            }
                                                            href={showAppointment(
                                                                appointment.id,
                                                            )}
                                                        >
                                                            View appointment
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <HistoryPagination
                            label="Past unresolved appointment pagination"
                            pagination={pastUnresolvedAppointments.pagination}
                        />
                    </Panel>
                ) : null}

                {props.auth.capabilities.viewAppointments &&
                appointmentHistory ? (
                    <Panel
                        className="scroll-mt-6 overflow-hidden"
                        id="appointment-history"
                    >
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="text-lg font-semibold text-text">
                                Cancelled and no-show appointments
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Historical scheduling outcomes ordered from
                                newest to oldest.
                            </p>
                        </div>
                        {appointmentHistory.data.length === 0 ? (
                            <EmptyState
                                description="Cancelled and no-show appointments remain visible here as administrative history."
                                title="No historical appointment outcomes"
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-3xl text-left text-sm">
                                    <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        <tr>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Appointment
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Scheduled for
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Outcome
                                            </th>
                                            <th
                                                className="px-5 py-4 text-right"
                                                scope="col"
                                            >
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {appointmentHistory.data.map(
                                            (appointment) => (
                                                <tr key={appointment.id}>
                                                    <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                        {
                                                            appointment.appointmentNumber
                                                        }
                                                    </td>
                                                    <td className="px-5 py-4 text-text-secondary">
                                                        <time
                                                            dateTime={
                                                                appointment.scheduledAt
                                                            }
                                                        >
                                                            {formatDateTime(
                                                                appointment.scheduledAt,
                                                            )}
                                                        </time>
                                                    </td>
                                                    <td className="px-5 py-4">
                                                        <StatusBadge
                                                            tone={appointmentStatusTone(
                                                                appointment
                                                                    .status
                                                                    .value,
                                                            )}
                                                        >
                                                            {
                                                                appointment
                                                                    .status
                                                                    .label
                                                            }
                                                        </StatusBadge>
                                                    </td>
                                                    <td className="px-5 py-4 text-right">
                                                        <Link
                                                            className={
                                                                textLinkStyles
                                                            }
                                                            href={showAppointment(
                                                                appointment.id,
                                                            )}
                                                        >
                                                            View appointment
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <HistoryPagination
                            label="Historical appointment pagination"
                            pagination={appointmentHistory.pagination}
                        />
                    </Panel>
                ) : null}
            </PageContainer>
        </>
    );
}

ShowPatient.layout = [AuthenticatedLayout];
