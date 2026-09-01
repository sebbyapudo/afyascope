import { Head, Link } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index } from '@/routes/check-ins';
import { show as showPatient } from '@/routes/patients';
import { show as showVisit } from '@/routes/visits';
import type { VisitCheckInDetail } from '@/types';

type CheckInShowProps = {
    checkIn: VisitCheckInDetail;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CheckInShow({ checkIn, status }: CheckInShowProps) {
    return (
        <>
            <Head title={checkIn.checkInNumber} />
            <PageContainer width="narrow">
                <PageHeader
                    actions={
                        <ActionLink
                            href={showVisit(checkIn.visit.id)}
                            variant="secondary"
                        >
                            View Visit
                        </ActionLink>
                    }
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to check-in queue
                        </Link>
                    }
                    description="Immutable Reception check-in record"
                    eyebrow={checkIn.checkInNumber}
                    title={checkIn.patient.name}
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
                    <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Reception check-in completed
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Completed {formatDateTime(checkIn.checkedInAt)}
                                {' by '}
                                {checkIn.checkedInBy}.
                            </p>
                        </div>
                        <StatusBadge tone="success">Checked In</StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={showPatient(checkIn.patient.id)}
                                >
                                    {checkIn.patient.name}
                                </Link>
                                <span className="mt-1 block text-sm text-text-secondary tabular-nums">
                                    {checkIn.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={showVisit(checkIn.visit.id)}
                                >
                                    {checkIn.visit.visitNumber}
                                </Link>
                                <span className="mt-1 block text-sm text-text-secondary">
                                    {formatDateTime(checkIn.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Financial clearance
                            </dt>
                            <dd className="mt-2 text-sm font-semibold text-brand-primary tabular-nums">
                                {checkIn.clearance.clearanceNumber}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="success">
                                    {checkIn.visit.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6">
                    <h2 className="font-semibold text-info">Next handoff</h2>
                    <p className="mt-1 text-sm leading-6 text-info">
                        {checkIn.visit.nextStep}. No consultation record has
                        been created by Reception check-in.
                    </p>
                </Panel>
            </PageContainer>
        </>
    );
}

CheckInShow.layout = [AuthenticatedLayout];
