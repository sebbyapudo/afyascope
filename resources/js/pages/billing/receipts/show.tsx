import { Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import {
    create as clearanceCreate,
    show as clearanceShow,
} from '@/routes/billing/clearances';
import { index as paymentIndex } from '@/routes/billing/payments';
import type { ReceiptDetail } from '@/types';

type ReceiptShowProps = {
    receipt: ReceiptDetail;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ReceiptShow({ receipt, status }: ReceiptShowProps) {
    const { props } = usePage();

    return (
        <>
            <Head title={receipt.receiptNumber} />
            <PageContainer width="narrow">
                <PageHeader
                    actions={
                        <div className="flex flex-wrap gap-3 print:hidden">
                            {receipt.bill.financialClearance &&
                            props.auth.capabilities.viewClearance ? (
                                <ActionLink
                                    href={clearanceShow(
                                        receipt.bill.financialClearance.id,
                                    )}
                                >
                                    View Clearance
                                </ActionLink>
                            ) : props.auth.capabilities.createClearance ? (
                                <ActionLink
                                    href={clearanceCreate(receipt.bill.id)}
                                >
                                    Grant Financial Clearance
                                </ActionLink>
                            ) : null}
                            <Button
                                onClick={() => window.print()}
                                type="button"
                                variant="secondary"
                            >
                                Print Receipt
                            </Button>
                        </div>
                    }
                    backLink={
                        <Link
                            className={`${textLinkStyles} print:hidden`}
                            href={paymentIndex()}
                        >
                            Back to consultation payments
                        </Link>
                    }
                    description="Consultation payment receipt"
                    eyebrow={receipt.receiptNumber}
                    title="Payment received"
                />

                {status ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success print:hidden"
                        role="status"
                    >
                        {status}
                    </p>
                ) : null}

                <Panel className="overflow-hidden print:border-text print:shadow-none">
                    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-border px-5 py-5 sm:px-8">
                        <div>
                            <p className="text-xl font-semibold text-text">
                                AfyaScope HMS
                            </p>
                            <p className="mt-1 text-sm text-text-secondary">
                                Official payment receipt
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm font-semibold text-brand-primary tabular-nums">
                                {receipt.receiptNumber}
                            </p>
                            <p className="mt-1 text-xs text-text-secondary">
                                Issued {formatDateTime(receipt.issuedAt)}
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-6 px-5 py-6 sm:grid-cols-2 sm:px-8">
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Received from
                            </p>
                            <p className="mt-2 font-semibold text-text">
                                {receipt.patient.name}
                            </p>
                            <p className="mt-1 text-sm text-text-secondary tabular-nums">
                                {receipt.patient.patientNumber}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                References
                            </p>
                            <dl className="mt-2 grid gap-1 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-text-secondary">
                                        Visit
                                    </dt>
                                    <dd className="font-medium text-text tabular-nums">
                                        {receipt.visit.visitNumber}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-text-secondary">
                                        Bill
                                    </dt>
                                    <dd className="font-medium text-text tabular-nums">
                                        {receipt.bill.billNumber}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-text-secondary">
                                        Payment
                                    </dt>
                                    <dd className="font-medium text-text tabular-nums">
                                        {receipt.payment.paymentNumber}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <div className="border-y border-border bg-surface-subtle px-5 py-6 sm:px-8">
                        <dl className="grid gap-5 sm:grid-cols-3">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Amount paid
                                </dt>
                                <dd className="mt-2 text-xl font-semibold text-text tabular-nums">
                                    {formatMinorAmount(
                                        receipt.payment.amountMinor,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Payment method
                                </dt>
                                <dd className="mt-2 text-sm font-medium text-text">
                                    {receipt.payment.method.label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Bill status
                                </dt>
                                <dd className="mt-2">
                                    <StatusBadge tone="success">
                                        {receipt.bill.status.label}
                                    </StatusBadge>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <dl className="grid gap-5 px-5 py-6 text-sm sm:grid-cols-2 sm:px-8">
                        <div>
                            <dt className="text-text-secondary">Recorded</dt>
                            <dd className="mt-1 font-medium text-text">
                                {formatDateTime(receipt.payment.recordedAt)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-text-secondary">Recorded by</dt>
                            <dd className="mt-1 font-medium text-text">
                                {receipt.payment.recordedBy}
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6 print:hidden">
                    <h2 className="font-semibold text-info">Next handoff</h2>
                    <p className="mt-1 text-sm leading-6 text-info">
                        {receipt.visit.nextStep}. Payment, financial clearance,
                        and check-in remain separate auditable actions.
                    </p>
                </Panel>
            </PageContainer>
        </>
    );
}

ReceiptShow.layout = [AuthenticatedLayout];
