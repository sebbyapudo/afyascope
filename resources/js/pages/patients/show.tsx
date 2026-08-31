import { Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
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
import type { PatientDetails, RecentVisit, UpcomingAppointment } from '@/types';

type ShowPatientProps = {
    patient: PatientDetails;
    recentVisits: RecentVisit[];
    upcomingAppointments: UpcomingAppointment[];
    status?: string | null;
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

export default function ShowPatient({
    patient,
    recentVisits,
    status,
    upcomingAppointments,
}: ShowPatientProps) {
    const { props } = usePage();
    const fields = [
        ['Full name', patient.name],
        ['Date of birth', formatDate(patient.dateOfBirth)],
        ['Sex', patient.sex?.label ?? 'Not recorded'],
        ['Phone number', patient.phone ?? 'Not recorded'],
        ['Email address', patient.email ?? 'Not recorded'],
        ['Address', patient.address ?? 'Not recorded'],
        [
            'Registered',
            patient.createdAt
                ? new Intl.DateTimeFormat(undefined, {
                      dateStyle: 'long',
                  }).format(new Date(patient.createdAt))
                : 'Not recorded',
        ],
    ];

    return (
        <>
            <Head title={patient.patientNumber} />
            <PageContainer>
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
                    description="Administrative Patient profile"
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
                        Demographics and contact
                    </h2>
                    <dl className="mt-6 grid gap-x-8 gap-y-6 sm:grid-cols-2">
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

                {props.auth.capabilities.viewAppointments ? (
                    <Panel className="overflow-hidden">
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="text-lg font-semibold text-text">
                                Upcoming appointments
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                The next five scheduled appointments for this
                                Patient.
                            </p>
                        </div>
                        {upcomingAppointments.length === 0 ? (
                            <div className="px-5 py-8 text-sm text-text-secondary">
                                No upcoming appointments are scheduled for this
                                Patient.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-2xl text-left text-sm">
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
                                        {upcomingAppointments.map(
                                            (appointment) => (
                                                <tr key={appointment.id}>
                                                    <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                        {
                                                            appointment.appointmentNumber
                                                        }
                                                    </td>
                                                    <td className="px-5 py-4 text-text-secondary">
                                                        {formatDateTime(
                                                            appointment.scheduledAt,
                                                        )}
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
                                                            View
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                ) : null}

                {props.auth.capabilities.viewVisits ? (
                    <Panel className="overflow-hidden">
                        <div className="border-b border-border px-5 py-4">
                            <h2 className="text-lg font-semibold text-text">
                                Recent Visits
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                The five most recent attendance episodes for
                                this Patient.
                            </p>
                        </div>
                        {recentVisits.length === 0 ? (
                            <div className="px-5 py-8 text-sm text-text-secondary">
                                No Visits have been created for this Patient.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-2xl text-left text-sm">
                                    <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        <tr>
                                            <th className="px-5 py-4">Visit</th>
                                            <th className="px-5 py-4">
                                                Occurred
                                            </th>
                                            <th className="px-5 py-4">
                                                Status
                                            </th>
                                            <th className="px-5 py-4 text-right">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {recentVisits.map((visit) => (
                                            <tr key={visit.id}>
                                                <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                    {visit.visitNumber}
                                                </td>
                                                <td className="px-5 py-4 text-text-secondary">
                                                    {formatDateTime(
                                                        visit.occurredAt,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <StatusBadge tone="info">
                                                        {visit.status.label}
                                                    </StatusBadge>
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
                                                        View
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                ) : null}
            </PageContainer>
        </>
    );
}

ShowPatient.layout = [AuthenticatedLayout];
