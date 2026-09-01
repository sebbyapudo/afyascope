import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { show as billShow } from '@/routes/billing/bills';
import { store } from '@/routes/billing/clearances';
import { show as receiptShow } from '@/routes/billing/receipts';
import type { FinancialClearanceQueueBill } from '@/types';

type CreateFinancialClearanceProps = {
    bill: FinancialClearanceQueueBill;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreateFinancialClearance({
    bill,
}: CreateFinancialClearanceProps) {
    return (
        <>
            <Head title={`Clearance ${bill.billNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={billShow(bill.id)}
                        >
                            Back to Bill
                        </Link>
                    }
                    description="Confirm the paid consultation obligation before granting the separate financial clearance event."
                    eyebrow={bill.billNumber}
                    title={`Clear ${bill.patient.name} for Reception`}
                />

                <Panel className="p-5 sm:p-8">
                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
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
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {bill.visit.visitNumber}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    {formatDateTime(bill.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Bill
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {bill.billNumber}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Payment
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text tabular-nums">
                                {bill.payment.paymentNumber}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    {formatDateTime(bill.payment.recordedAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Paid amount
                            </dt>
                            <dd className="mt-2 text-lg font-semibold text-text tabular-nums">
                                {formatMinorAmount(bill.payment.amountMinor)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Receipt
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={receiptShow(bill.payment.receipt.id)}
                                >
                                    {bill.payment.receipt.receiptNumber}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Financial state
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="warning">
                                    Awaiting financial clearance
                                </StatusBadge>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <Form action={store(bill.id)}>
                        {({ errors, processing }) => (
                            <div className="grid gap-6">
                                {errors.bill || errors.visit ? (
                                    <p
                                        className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                        role="alert"
                                    >
                                        {errors.bill ?? errors.visit}
                                    </p>
                                ) : null}

                                <div className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm leading-6 text-info">
                                    Granting clearance creates an immutable
                                    financial record. The Visit remains Created
                                    and moves to “Awaiting Reception check-in”;
                                    this action does not check the Patient in.
                                </div>

                                <div className="flex flex-wrap justify-end gap-3 border-t border-border pt-6">
                                    <Link
                                        className={textLinkStyles}
                                        href={billShow(bill.id)}
                                    >
                                        Cancel
                                    </Link>
                                    <Button disabled={processing} type="submit">
                                        {processing
                                            ? 'Granting…'
                                            : 'Grant Financial Clearance'}
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

CreateFinancialClearance.layout = [AuthenticatedLayout];
