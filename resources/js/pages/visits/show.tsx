import { Head, Link } from '@inertiajs/react';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as showPatient } from '@/routes/patients';
import { index } from '@/routes/visits';
import type { VisitSummary } from '@/types';

type ShowVisitProps = { status?: string | null; visit: VisitSummary };

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ShowVisit({ status, visit }: ShowVisitProps) {
    return (
        <>
            <Head title={visit.visitNumber} />
            <PageContainer>
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to Visit registry
                        </Link>
                    }
                    description="Administrative Visit record"
                    eyebrow={visit.visitNumber}
                    title={visit.patient.name}
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
                                    href={showPatient(visit.patient.id)}
                                >
                                    {visit.patient.name}
                                </Link>
                                <span className="mt-1 block text-sm text-text-secondary tabular-nums">
                                    {visit.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Occurred
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {formatDateTime(visit.occurredAt)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Current status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="info">
                                    {visit.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                    </dl>
                </Panel>
                <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6">
                    <h2 className="font-semibold text-info">Next handoff</h2>
                    <p className="mt-1 text-sm text-info">{visit.nextStep}</p>
                </Panel>
            </PageContainer>
        </>
    );
}

ShowVisit.layout = [AuthenticatedLayout];
