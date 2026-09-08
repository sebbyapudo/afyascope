import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, store } from '@/routes/nursing/recovery';
import type { RecoveryProcedureContext } from '@/types';

type RecoveryCreateProps = {
    procedure: RecoveryProcedureContext;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function RecoveryCreate({ procedure }: RecoveryCreateProps) {
    return (
        <>
            <Head title="Start Nursing recovery" />
            <PageContainer>
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to recovery queue
                        </Link>
                    }
                    description="Confirm the completed procedure context before taking responsibility for this recovery episode."
                    title="Start Nursing recovery"
                />

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Completed procedure context
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Starting recovery creates the immutable
                                Nurse-owned recovery record. It does not record
                                observations or complete recovery.
                            </p>
                        </div>
                        <StatusBadge tone="warning">
                            Ready for Nursing recovery
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {procedure.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {procedure.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {procedure.visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(procedure.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Procedure
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {procedure.procedure.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {procedure.procedureNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Doctor
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {procedure.doctor.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    Completed{' '}
                                    {procedure.completedAt
                                        ? formatDateTime(procedure.completedAt)
                                        : 'time not recorded'}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <Form
                        {...store.form(procedure.id)}
                        className="mt-8 border-t border-border pt-6"
                    >
                        {({ errors, processing }) => (
                            <div className="grid gap-4">
                                {errors.procedure ? (
                                    <p
                                        className="text-sm text-danger"
                                        role="alert"
                                    >
                                        {errors.procedure}
                                    </p>
                                ) : null}
                                <div>
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Starting…'
                                            : 'Start recovery'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>
            </PageContainer>
        </>
    );
}

RecoveryCreate.layout = [AuthenticatedLayout];
