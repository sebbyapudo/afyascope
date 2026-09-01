import { Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index } from '@/routes/billing/consultations';
import { create as paymentCreate } from '@/routes/billing/payments';
import { show as receiptShow } from '@/routes/billing/receipts';
import type { ConsultationBill } from '@/types';

type ShowBillProps = {
    bill: ConsultationBill;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ShowBill({ bill, status }: ShowBillProps) {
    const { props } = usePage();
    const canRecordPayment =
        props.auth.capabilities.createPayments && bill.payment === null;

    return (
        <>
            <Head title={bill.billNumber} />
            <PageContainer>
                <PageHeader
                    actions={
                        canRecordPayment ? (
                            <ActionLink href={paymentCreate(bill.id)}>
                                Record Payment
                            </ActionLink>
                        ) : bill.payment?.receipt ? (
                            <ActionLink
                                href={receiptShow(bill.payment.receipt.id)}
                                variant="secondary"
                            >
                                View Receipt
                            </ActionLink>
                        ) : null
                    }
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to consultation billing queue
                        </Link>
                    }
                    description="Consultation charge record"
                    eyebrow={bill.billNumber}
                    title={bill.patient.name}
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
                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {bill.patient.name}
                                <span className="mt-1 block font-normal text-text-secondary tabular-nums">
                                    {bill.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-brand-primary tabular-nums">
                                {bill.visit.visitNumber}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    {formatDateTime(bill.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Bill status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge
                                    tone={
                                        bill.status.value === 'paid'
                                            ? 'success'
                                            : 'warning'
                                    }
                                >
                                    {bill.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Bill type
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {bill.type.label}
                            </dd>
                        </div>
                        {bill.createdAt ? (
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Created
                                </dt>
                                <dd className="mt-2 text-sm text-text">
                                    {formatDateTime(bill.createdAt)}
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </Panel>

                <Panel className="overflow-hidden">
                    <div className="border-b border-border px-5 py-4">
                        <h2 className="text-lg font-semibold text-text">
                            Bill items
                        </h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-xl text-left text-sm">
                            <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                <tr>
                                    <th className="px-5 py-4" scope="col">
                                        Service
                                    </th>
                                    <th
                                        className="px-5 py-4 text-right"
                                        scope="col"
                                    >
                                        Amount
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {bill.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-5 py-4 font-medium text-text">
                                            {item.description}
                                        </td>
                                        <td className="px-5 py-4 text-right text-text tabular-nums">
                                            {formatMinorAmount(
                                                item.amountMinor,
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t border-border bg-surface-subtle">
                                <tr>
                                    <th className="px-5 py-4 text-right font-semibold text-text">
                                        Total
                                    </th>
                                    <td className="px-5 py-4 text-right font-semibold text-text tabular-nums">
                                        {formatMinorAmount(
                                            bill.totalAmountMinor,
                                        )}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </Panel>

                {bill.payment ? (
                    <Panel className="p-5 sm:p-8">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold text-text">
                                    Payment
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Exact consultation Bill payment recorded.
                                </p>
                            </div>
                            {bill.payment.receipt ? (
                                <ActionLink
                                    href={receiptShow(bill.payment.receipt.id)}
                                    size="small"
                                    variant="secondary"
                                >
                                    {bill.payment.receipt.receiptNumber}
                                </ActionLink>
                            ) : null}
                        </div>
                        <dl className="mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-3">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Payment reference
                                </dt>
                                <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                    {bill.payment.paymentNumber}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Method
                                </dt>
                                <dd className="mt-2 text-sm text-text">
                                    {bill.payment.method.label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Amount paid
                                </dt>
                                <dd className="mt-2 text-sm font-semibold text-text tabular-nums">
                                    {formatMinorAmount(
                                        bill.payment.amountMinor,
                                    )}
                                </dd>
                            </div>
                        </dl>
                    </Panel>
                ) : null}

                <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6">
                    <h2 className="font-semibold text-info">Next handoff</h2>
                    <p className="mt-1 text-sm leading-6 text-info">
                        {bill.visit.nextStep}. Financial clearance and check-in
                        remain separate later actions.
                    </p>
                </Panel>
            </PageContainer>
        </>
    );
}

ShowBill.layout = [AuthenticatedLayout];
