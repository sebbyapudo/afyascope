import { Head, Link } from '@inertiajs/react';
import { PatientForm } from '@/components/patients/patient-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show, update } from '@/routes/patients';
import type { PatientDetails, PatientSexOption } from '@/types';

type EditPatientProps = {
    patient: PatientDetails;
    sexOptions: PatientSexOption[];
};

export default function EditPatient({ patient, sexOptions }: EditPatientProps) {
    return (
        <>
            <Head title={`Edit ${patient.patientNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={show(patient.id)}
                        >
                            Back to Patient profile
                        </Link>
                    }
                    description={`${patient.patientNumber} is permanent and cannot be changed.`}
                    title="Edit Patient demographics"
                />

                <Panel className="p-5 sm:p-8">
                    <PatientForm
                        form={update.form(patient.id)}
                        patient={patient}
                        sexOptions={sexOptions}
                        submitLabel="Save changes"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

EditPatient.layout = [AuthenticatedLayout];
