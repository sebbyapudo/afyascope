import { Form, Head, Link } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as showAppointment } from '@/routes/appointments';
import { store as storeFromAppointment } from '@/routes/appointments/visit';
import { show as showPatient } from '@/routes/patients';
import { store } from '@/routes/patients/visits';
import { show as showVisit } from '@/routes/visits';
import type { VisitPatient } from '@/types';

type AppointmentHandoff = {
    id: number;
    appointmentNumber: string;
    scheduledAt: string;
    isScheduled: boolean;
    linkedVisit: { id: number; visitNumber: string } | null;
};

type CreateVisitProps = {
    appointment?: AppointmentHandoff;
    patient: VisitPatient;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreateVisit({
    appointment,
    patient,
}: CreateVisitProps) {
    return (
        <>
            <Head title="Start new Visit" />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={
                                appointment
                                    ? showAppointment(appointment.id)
                                    : showPatient(patient.id)
                            }
                        >
                            {appointment
                                ? 'Back to Appointment'
                                : 'Back to Patient profile'}
                        </Link>
                    }
                    description={
                        appointment
                            ? 'Confirm the scheduling record and existing Patient before creating the attendance Visit.'
                            : 'Confirm the existing Patient before creating a new attendance episode.'
                    }
                    title="Start new Visit"
                />
                <Panel className="p-5 sm:p-8">
                    <dl className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {patient.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient reference
                            </dt>
                            <dd className="mt-2 font-medium text-brand-primary tabular-nums">
                                {patient.patientNumber}
                            </dd>
                        </div>
                        {appointment ? (
                            <>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Appointment
                                    </dt>
                                    <dd className="mt-2 font-medium text-brand-primary tabular-nums">
                                        {appointment.appointmentNumber}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Scheduled for
                                    </dt>
                                    <dd className="mt-2 text-sm text-text">
                                        {formatDateTime(
                                            appointment.scheduledAt,
                                        )}
                                    </dd>
                                </div>
                            </>
                        ) : null}
                    </dl>
                    <p className="mt-6 border-t border-border pt-6 text-sm leading-6 text-text-secondary">
                        The Visit reference and occurrence time will be assigned
                        securely when the Visit is created.
                    </p>
                    {appointment?.linkedVisit ? (
                        <div className="mt-6 rounded-control border border-info-border bg-info-soft p-4 text-sm text-info">
                            <p>
                                Visit {appointment.linkedVisit.visitNumber} has
                                already been created from this Appointment.
                            </p>
                            <div className="mt-3">
                                <ActionLink
                                    href={showVisit(appointment.linkedVisit.id)}
                                >
                                    View existing Visit
                                </ActionLink>
                            </div>
                        </div>
                    ) : appointment && !appointment.isScheduled ? (
                        <p
                            className="mt-6 rounded-control border border-warning-border bg-warning-soft p-4 text-sm text-warning"
                            role="status"
                        >
                            Only a scheduled Appointment can start a Visit.
                        </p>
                    ) : (
                        <Form
                            {...(appointment
                                ? storeFromAppointment.form(appointment.id)
                                : store.form(patient.id))}
                        >
                            {({ errors, processing }) => (
                                <div className="mt-6 flex flex-wrap gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Creating…'
                                            : 'Create Visit'}
                                    </Button>
                                    {errors.appointment ? (
                                        <p
                                            className="basis-full text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.appointment}
                                        </p>
                                    ) : null}
                                </div>
                            )}
                        </Form>
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}

CreateVisit.layout = [AuthenticatedLayout];
