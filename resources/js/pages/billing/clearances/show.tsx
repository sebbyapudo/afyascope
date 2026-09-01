import { Head, Link } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { show as billShow } from '@/routes/billing/bills';
import { index } from '@/routes/billing/clearances';
import { show as receiptShow } from '@/routes/billing/receipts';
import type { FinancialClearanceDetail } from '@/types';

type FinancialClearanceShowProps = {
    clearance: FinancialClearanceDetail;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function FinancialClearanceShow({
    clearance,
    status,
}: FinancialClearanceShowProps) {
    return (
        <>
            <Head title={clearance.clearanceNumber} />
            <PageContainer width="narrow">
                <PageHeader
                    actions={
                        <ActionLink
                            href={billShow(clearance.bill.id)}
                            variant="secondary"
                        >
                            View Bill
                        </ActionLink>
                    }
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to financial clearance queue
                        </Link>
                    }
                    description="Immutable consultation financial clearance record"
                    eyebrow={clearance.clearanceNumber}
                    title={clearance.patient.name}
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
                                Consultation financially cleared
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Granted {formatDateTime(clearance.grantedAt)} by{' '}
                                {clearance.grantedBy}.
                            </p>
                        </div>
                        <StatusBadge tone="success">
                            Financially cleared
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {clearance.patient.name}
                                <span className="mt-1 block font-normal text-text-secondary tabular-nums">
                                    {clearance.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {clearance.visit.visitNumber}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    {formatDateTime(clearance.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Bill
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {clearance.bill.billNumber}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Payment
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {clearance.payment.paymentNumber}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Amount paid
                            </dt>
                            <dd className="mt-2 text-lg font-semibold text-text tabular-nums">
                                {formatMinorAmount(
                                    clearance.payment.amountMinor,
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Receipt
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={receiptShow(
                                        clearance.payment.receipt.id,
                                    )}
                                >
                                    {clearance.payment.receipt.receiptNumber}
                                </Link>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6">
                    <h2 className="font-semibold text-info">Next handoff</h2>
                    <p className="mt-1 text-sm leading-6 text-info">
                        {clearance.visit.nextStep}. The Visit remains{' '}
                        {clearance.visit.status.label}; Reception check-in is
                        not part of this action.
                    </p>
                </Panel>
            </PageContainer>
        </>
    );
}

FinancialClearanceShow.layout = [AuthenticatedLayout];
