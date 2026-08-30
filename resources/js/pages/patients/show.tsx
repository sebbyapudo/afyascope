import { Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { edit, index } from '@/routes/patients';
import type { PatientDetails } from '@/types';

type ShowPatientProps = {
    patient: PatientDetails;
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

export default function ShowPatient({ patient, status }: ShowPatientProps) {
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
                        props.auth.capabilities.updatePatients ? (
                            <ActionLink href={edit(patient.id)}>
                                Edit demographics
                            </ActionLink>
                        ) : null
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
            </PageContainer>
        </>
    );
}

ShowPatient.layout = [AuthenticatedLayout];
