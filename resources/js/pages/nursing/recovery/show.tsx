import { Head, Link } from '@inertiajs/react';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as procedureShow } from '@/routes/clinical/procedures';
import type { RecoveryWorkspace } from '@/types';

type RecoveryShowProps = {
    recovery: RecoveryWorkspace;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function RecoveryShow({ recovery, status }: RecoveryShowProps) {
    return (
        <>
            <Head title={recovery.recoveryNumber} />
            <PageContainer>
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={procedureShow(recovery.procedure.id)}
                        >
                            Back to procedure
                        </Link>
                    }
                    description="Structural recovery context only. Observation and completion workflows are not part of this checkpoint."
                    eyebrow="Nursing recovery"
                    title={recovery.recoveryNumber}
                />

                {status ? (
                    <div
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {status}
                    </div>
                ) : null}

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Recovery context
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                {recovery.canManage
                                    ? 'You are the responsible Nurse for this in-progress recovery episode.'
                                    : `Read-only record owned by ${recovery.nurse.name}.`}
                            </p>
                        </div>
                        <StatusBadge tone="info">
                            {recovery.status.label}
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {recovery.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {recovery.visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(recovery.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Procedure
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.procedure.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {recovery.procedure.procedureNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Doctor
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.doctor.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    Procedure authority
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Nurse
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.nurse.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    Started {formatDateTime(recovery.startedAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Workflow state
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-info">
                                {recovery.visit.nextStep}
                            </dd>
                        </div>
                    </dl>
                </Panel>
            </PageContainer>
        </>
    );
}

RecoveryShow.layout = [AuthenticatedLayout];
