import { Head, Link } from '@inertiajs/react';
import { PatientForm } from '@/components/patients/patient-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, store } from '@/routes/patients';
import type { PatientSexOption } from '@/types';

type CreatePatientProps = {
    sexOptions: PatientSexOption[];
};

export default function CreatePatient({ sexOptions }: CreatePatientProps) {
    return (
        <>
            <Head title="Register Patient" />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to Patient registry
                        </Link>
                    }
                    description="Create a persistent administrative Patient record. A Patient reference is generated automatically."
                    title="Register Patient"
                />

                <Panel className="p-5 sm:p-8">
                    <PatientForm
                        form={store.form()}
                        sexOptions={sexOptions}
                        submitLabel="Register Patient"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

CreatePatient.layout = [AuthenticatedLayout];
