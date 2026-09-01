import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, store } from '@/routes/check-ins';
import { show as showPatient } from '@/routes/patients';
import { show as showVisit } from '@/routes/visits';
import type { CheckInQueueVisit } from '@/types';

type CreateCheckInProps = { visit: CheckInQueueVisit };

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreateCheckIn({ visit }: CreateCheckInProps) {
    return (
        <>
            <Head title={`Check in ${visit.visitNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to check-in queue
                        </Link>
                    }
                    description="Confirm the financially cleared Visit before handing it to Doctor consultation."
                    eyebrow={visit.visitNumber}
                    title={`Check in ${visit.patient.name}`}
                />

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
                                Visit
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={showVisit(visit.id)}
                                >
                                    {visit.visitNumber}
                                </Link>
                                <span className="mt-1 block text-sm text-text-secondary">
                                    {formatDateTime(visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Consultation clearance
                            </dt>
                            <dd className="mt-2 text-sm font-semibold text-brand-primary tabular-nums">
                                {visit.clearance.clearanceNumber}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    Granted{' '}
                                    {formatDateTime(visit.clearance.grantedAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Current state
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="success">
                                    Financially cleared
                                </StatusBadge>
                                <span className="mt-2 block text-sm text-text-secondary">
                                    {visit.nextStep}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <Form action={store(visit.id)}>
                        {({ errors, processing }) => (
                            <div className="grid gap-6">
                                {errors.visit ? (
                                    <p
                                        className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                        role="alert"
                                    >
                                        {errors.visit}
                                    </p>
                                ) : null}
                                <div className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm leading-6 text-info">
                                    Check-in creates an immutable Reception
                                    record and moves the Visit to “Ready for
                                    Doctor consultation”. It does not create or
                                    open a consultation.
                                </div>
                                <div className="flex flex-wrap justify-end gap-3 border-t border-border pt-6">
                                    <Link
                                        className={textLinkStyles}
                                        href={showVisit(visit.id)}
                                    >
                                        Cancel
                                    </Link>
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Checking in…'
                                            : 'Complete Check-in'}
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

CreateCheckIn.layout = [AuthenticatedLayout];
