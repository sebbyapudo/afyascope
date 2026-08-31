import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as showPatient } from '@/routes/patients';
import { store } from '@/routes/patients/visits';
import type { VisitPatient } from '@/types';

type CreateVisitProps = { patient: VisitPatient };

export default function CreateVisit({ patient }: CreateVisitProps) {
    return (
        <>
            <Head title="Start new Visit" />
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
                    description="Confirm the existing Patient before creating a new attendance episode."
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
                    </dl>
                    <p className="mt-6 border-t border-border pt-6 text-sm leading-6 text-text-secondary">
                        The Visit reference and occurrence time will be assigned
                        securely when the Visit is created.
                    </p>
                    <Form {...store.form(patient.id)}>
                        {({ processing }) => (
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Button disabled={processing} type="submit">
                                    {processing ? 'Creating…' : 'Create Visit'}
                                </Button>
                            </div>
                        )}
                    </Form>
                </Panel>
            </PageContainer>
        </>
    );
}

CreateVisit.layout = [AuthenticatedLayout];
