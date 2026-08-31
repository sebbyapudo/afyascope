import { Head, Link } from '@inertiajs/react';
import { AppointmentForm } from '@/components/appointments/appointment-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show, update } from '@/routes/appointments';
import type { AppointmentSummary } from '@/types';

type EditAppointmentProps = { appointment: AppointmentSummary };

export default function EditAppointment({ appointment }: EditAppointmentProps) {
    return (
        <>
            <Head title={`Reschedule ${appointment.appointmentNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={show(appointment.id)}
                        >
                            Back to appointment
                        </Link>
                    }
                    description={`Change the scheduled time for ${appointment.patient.name}.`}
                    eyebrow={appointment.appointmentNumber}
                    title="Reschedule appointment"
                />
                <Panel className="p-5 sm:p-8">
                    <AppointmentForm
                        defaultScheduledAt={appointment.scheduledAt}
                        form={update.form(appointment.id)}
                        submitLabel="Save new schedule"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

EditAppointment.layout = [AuthenticatedLayout];
