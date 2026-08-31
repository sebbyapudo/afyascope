import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { cancel, edit, noShow } from '@/routes/appointments';
import { create as createVisit } from '@/routes/appointments/visit';
import { show as showPatient } from '@/routes/patients';
import { show as showVisit } from '@/routes/visits';
import type { AppointmentStatus, AppointmentSummary } from '@/types';

type ShowAppointmentProps = {
    appointment: AppointmentSummary;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
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

export default function ShowAppointment({
    appointment,
    status,
}: ShowAppointmentProps) {
    const { props } = usePage();
    const canChange =
        props.auth.capabilities.updateAppointments && appointment.isScheduled;
    const canStartVisit =
        props.auth.capabilities.createVisits &&
        appointment.isScheduled &&
        appointment.linkedVisit === null;

    return (
        <>
            <Head title={appointment.appointmentNumber} />
            <PageContainer>
                <PageHeader
                    actions={
                        canStartVisit || canChange ? (
                            <div className="flex flex-wrap gap-3">
                                {canStartVisit ? (
                                    <ActionLink
                                        href={createVisit(appointment.id)}
                                    >
                                        Start Visit
                                    </ActionLink>
                                ) : null}
                                {canChange ? (
                                    <ActionLink
                                        href={edit(appointment.id)}
                                        variant={
                                            canStartVisit
                                                ? 'secondary'
                                                : 'primary'
                                        }
                                    >
                                        Reschedule
                                    </ActionLink>
                                ) : null}
                            </div>
                        ) : null
                    }
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={showPatient(appointment.patient.id)}
                        >
                            Back to Patient profile
                        </Link>
                    }
                    description="Administrative scheduling record"
                    eyebrow={appointment.appointmentNumber}
                    title={appointment.patient.name}
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
                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={showPatient(appointment.patient.id)}
                                >
                                    {appointment.patient.name}
                                </Link>
                                <span className="mt-1 block text-sm text-text-secondary tabular-nums">
                                    {appointment.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Scheduled date and time
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {formatDateTime(appointment.scheduledAt)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge
                                    tone={statusTone(appointment.status.value)}
                                >
                                    {appointment.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                        {appointment.linkedVisit ? (
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Attendance Visit
                                </dt>
                                <dd className="mt-2">
                                    <Link
                                        className={textLinkStyles}
                                        href={showVisit(
                                            appointment.linkedVisit.id,
                                        )}
                                    >
                                        {appointment.linkedVisit.visitNumber}
                                    </Link>
                                    <span className="mt-1 block text-sm text-text-secondary">
                                        {appointment.linkedVisit.nextStep}
                                    </span>
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </Panel>

                {canStartVisit ? (
                    <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6">
                        <h2 className="font-semibold text-info">
                            Patient attendance
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-info">
                            Start a Visit for this existing Patient. The
                            Appointment will remain available as scheduling
                            history.
                        </p>
                        <div className="mt-4">
                            <ActionLink href={createVisit(appointment.id)}>
                                Start Visit from Appointment
                            </ActionLink>
                        </div>
                    </Panel>
                ) : null}

                {canChange ? (
                    <Panel className="p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-text">
                            Appointment outcome
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-text-secondary">
                            Cancellation and no-show preserve this scheduling
                            record as history. Neither action creates a Visit.
                        </p>
                        <div className="mt-5 flex flex-wrap gap-3">
                            <Form {...noShow.form(appointment.id)}>
                                {({ errors, processing }) => (
                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                            variant="secondary"
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Mark no-show'}
                                        </Button>
                                        {errors.status ? (
                                            <p
                                                className="mt-2 text-sm text-danger"
                                                role="alert"
                                            >
                                                {errors.status}
                                            </p>
                                        ) : null}
                                    </div>
                                )}
                            </Form>
                            <Form {...cancel.form(appointment.id)}>
                                {({ errors, processing }) => (
                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                            variant="danger"
                                        >
                                            {processing
                                                ? 'Cancelling…'
                                                : 'Cancel appointment'}
                                        </Button>
                                        {errors.status ? (
                                            <p
                                                className="mt-2 text-sm text-danger"
                                                role="alert"
                                            >
                                                {errors.status}
                                            </p>
                                        ) : null}
                                    </div>
                                )}
                            </Form>
                        </div>
                    </Panel>
                ) : null}
            </PageContainer>
        </>
    );
}

ShowAppointment.layout = [AuthenticatedLayout];
