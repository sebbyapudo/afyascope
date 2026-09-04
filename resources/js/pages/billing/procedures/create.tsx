import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index, store } from '@/routes/billing/procedures';
import type { ProcedureBillingHandoff } from '@/types';

type CreateProcedureBillProps = {
    handoff: ProcedureBillingHandoff;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreateProcedureBill({
    handoff,
}: CreateProcedureBillProps) {
    return (
        <>
            <Head title={`Bill ${handoff.visit.visitNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to procedure billing queue
                        </Link>
                    }
                    description="Confirm the Doctor's procedure handoff. The selected service name and current catalog price will be preserved on the Bill."
                    eyebrow={handoff.handoffNumber}
                    title={`Create procedure Bill for ${handoff.patient.name}`}
                />

                <Panel className="p-5 sm:p-8">
                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {handoff.patient.name}
                                <span className="mt-1 block font-normal text-text-secondary tabular-nums">
                                    {handoff.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {handoff.visit.visitNumber}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    {formatDateTime(handoff.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Procedure
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {handoff.procedure.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Catalog price
                            </dt>
                            <dd className="mt-2 text-lg font-semibold text-text tabular-nums">
                                {formatMinorAmount(
                                    handoff.procedure.amountMinor,
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Doctor decision
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {handoff.decidedBy}
                                <span className="mt-1 block text-text-secondary tabular-nums">
                                    {handoff.decisionNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Current state
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="warning">
                                    {handoff.visit.nextStep}
                                </StatusBadge>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <Form action={store(handoff.id)}>
                        {({ errors, processing }) => (
                            <div className="grid gap-6">
                                {errors.procedure_billing_handoff ||
                                errors.visit ? (
                                    <p
                                        className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                        role="alert"
                                    >
                                        {errors.procedure_billing_handoff ??
                                            errors.visit}
                                    </p>
                                ) : null}

                                <p className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm leading-6 text-info">
                                    This creates the second financial gate only.
                                    Payment, receipt, and procedure financial
                                    clearance remain separate auditable actions.
                                </p>

                                <div className="flex flex-wrap justify-end gap-3 border-t border-border pt-6">
                                    <Link
                                        className={textLinkStyles}
                                        href={index()}
                                    >
                                        Cancel
                                    </Link>
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Creating…'
                                            : 'Create Procedure Bill'}
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

CreateProcedureBill.layout = [AuthenticatedLayout];
