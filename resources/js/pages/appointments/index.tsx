import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, show } from '@/routes/appointments';
import { index as patientIndex } from '@/routes/patients';
import type {
    AppointmentPage,
    AppointmentStatus,
    AppointmentStatusOption,
} from '@/types';

type AppointmentIndexProps = {
    appointments: AppointmentPage;
    filters: {
        awaitingAttendance: boolean;
        date: string;
        q: string;
        status: string;
    };
    statusOptions: AppointmentStatusOption[];
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

function statusTone(status: AppointmentStatus['value']) {
    if (status === 'cancelled') {
        return 'danger' as const;
    }

    if (status === 'no_show') {
        return 'warning' as const;
    }

    return 'info' as const;
}

export default function AppointmentIndex({
    appointments,
    filters,
    statusOptions,
}: AppointmentIndexProps) {
    const { props } = usePage();
    const { data, pagination } = appointments;
    const hasFilters = Boolean(
        filters.q ||
        filters.date ||
        filters.status ||
        filters.awaitingAttendance,
    );

    return (
        <>
            <Head title="Appointments" />
            <PageContainer width="wide">
                <PageHeader
                    actions={
                        props.auth.capabilities.createAppointments ? (
                            <ActionLink href={patientIndex()}>
                                Find Patient to schedule
                            </ActionLink>
                        ) : null
                    }
                    description="Review scheduling history and isolate scheduled appointments still awaiting attendance."
                    title="Appointment registry"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ processing }) => (
                            <div className="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_12rem_12rem_minmax(12rem,auto)_auto] lg:items-end">
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="appointment-search"
                                    >
                                        Search appointments
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.q}
                                        id="appointment-search"
                                        maxLength={100}
                                        name="q"
                                        placeholder="Appointment or Patient reference, or name"
                                        type="search"
                                    />
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="appointment-date"
                                    >
                                        Scheduled date
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.date}
                                        id="appointment-date"
                                        name="date"
                                        type="date"
                                    />
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="appointment-status"
                                    >
                                        Status
                                    </label>
                                    <select
                                        className={formControlStyles}
                                        defaultValue={filters.status}
                                        id="appointment-status"
                                        name="status"
                                    >
                                        <option value="">All statuses</option>
                                        {statusOptions.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <label className="flex min-h-10 items-center gap-3 rounded-control border border-border bg-surface px-3 py-2 text-sm font-medium text-text">
                                    <input
                                        defaultChecked={
                                            filters.awaitingAttendance
                                        }
                                        className="size-4 rounded border-border text-brand-primary focus:ring-brand-primary"
                                        name="awaiting_attendance"
                                        type="checkbox"
                                        value="1"
                                    />
                                    Awaiting attendance only
                                </label>
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
                                    ? 'No appointment records match the selected filters.'
                                    : 'Scheduled appointments will appear here.'
                            }
                            title={
                                hasFilters
                                    ? 'No matching appointments'
                                    : 'No appointments scheduled'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Appointment
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Scheduled
                                        </th>
                                        <th className="px-5 py-4" scope="col">
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
                                    {data.map((appointment) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={appointment.id}
                                        >
                                            <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                {appointment.appointmentNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {appointment.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {
                                                        appointment.patient
                                                            .patientNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(
                                                    appointment.scheduledAt,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge
                                                    tone={statusTone(
                                                        appointment.status
                                                            .value,
                                                    )}
                                                >
                                                    {appointment.status.label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={show(appointment.id)}
                                                >
                                                    View
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
                                aria-label="Appointment registry pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                page:
                                                    pagination.currentPage - 1,
                                                q: filters.q || undefined,
                                                date: filters.date || undefined,
                                                status:
                                                    filters.status || undefined,
                                                awaiting_attendance:
                                                    filters.awaitingAttendance
                                                        ? 1
                                                        : undefined,
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
                                                page:
                                                    pagination.currentPage + 1,
                                                q: filters.q || undefined,
                                                date: filters.date || undefined,
                                                status:
                                                    filters.status || undefined,
                                                awaiting_attendance:
                                                    filters.awaitingAttendance
                                                        ? 1
                                                        : undefined,
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

AppointmentIndex.layout = [AuthenticatedLayout];
