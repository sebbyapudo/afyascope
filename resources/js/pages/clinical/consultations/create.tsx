import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, store } from '@/routes/clinical/consultations';
import type { ClinicalVisitContext } from '@/types';

type CreateConsultationProps = { visit: ClinicalVisitContext };

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreateConsultation({ visit }: CreateConsultationProps) {
    return (
        <>
            <Head title={`Begin consultation for ${visit.visitNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to Doctor queue
                        </Link>
                    }
                    description="Confirm the checked-in Patient and Visit before taking responsibility for this consultation."
                    eyebrow={visit.visitNumber}
                    title={`Begin consultation for ${visit.patient.name}`}
                />

                <Panel className="p-5 sm:p-8">
                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {visit.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {visit.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Reception check-in
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {formatDateTime(visit.checkIn.checkedInAt)}
                                <span className="mt-1 block text-text-secondary tabular-nums">
                                    {visit.checkIn.checkInNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Current state
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="success">
                                    {visit.status.label}
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
                                {errors.visit || errors.actor ? (
                                    <p
                                        className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                        role="alert"
                                    >
                                        {errors.visit ?? errors.actor}
                                    </p>
                                ) : null}
                                <div className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm leading-6 text-info">
                                    Beginning consultation creates one immutable
                                    consultation record, assigns it to you, and
                                    changes the workflow message to
                                    “Consultation in progress”. No assessment or
                                    procedure record is created here.
                                </div>
                                <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-6">
                                    <Link
                                        className={textLinkStyles}
                                        href={index()}
                                    >
                                        Cancel
                                    </Link>
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Beginning consultation…'
                                            : 'Begin Consultation'}
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

CreateConsultation.layout = [AuthenticatedLayout];
