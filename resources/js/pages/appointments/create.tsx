import { Head, Link } from '@inertiajs/react';
import { AppointmentForm } from '@/components/appointments/appointment-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as showPatient } from '@/routes/patients';
import { store } from '@/routes/patients/appointments';
import type { AppointmentPatient } from '@/types';

type CreateAppointmentProps = { patient: AppointmentPatient };

export default function CreateAppointment({ patient }: CreateAppointmentProps) {
    return (
        <>
            <Head title="Schedule appointment" />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={showPatient(patient.id)}
                        >
                            Back to Patient profile
                        </Link>
                    }
                    description="Create a scheduling record for this existing Patient. This does not create a Visit."
                    eyebrow={patient.patientNumber}
                    title={`Schedule appointment for ${patient.name}`}
                />
                <Panel className="p-5 sm:p-8">
                    <AppointmentForm
                        form={store.form(patient.id)}
                        submitLabel="Schedule appointment"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

CreateAppointment.layout = [AuthenticatedLayout];
