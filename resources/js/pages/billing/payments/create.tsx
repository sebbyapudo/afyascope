import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { show as billShow } from '@/routes/billing/bills';
import { store } from '@/routes/billing/payments';
import type { PaymentMethodOption, PaymentQueueBill } from '@/types';

type CreatePaymentProps = {
    bill: PaymentQueueBill;
    paymentMethods: PaymentMethodOption[];
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreatePayment({
    bill,
    paymentMethods,
}: CreatePaymentProps) {
    return (
        <>
            <Head title={`Payment ${bill.billNumber}`} />
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
                    description="Confirm the locally recorded payment method. The exact Bill total is controlled by the server and cannot be edited."
                    eyebrow={bill.billNumber}
                    title={`Record payment for ${bill.patient.name}`}
                />

                <Panel className="p-5 sm:p-6">
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
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
                                Financial gate
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {bill.type.label}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Bill status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="warning">
                                    {bill.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Exact amount due
                            </dt>
                            <dd className="mt-2 text-lg font-semibold text-text tabular-nums">
                                {formatMinorAmount(bill.totalAmountMinor)}
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <Form action={store(bill.id)}>
                        {({ errors, processing }) => (
                            <div className="grid gap-6">
                                {errors.bill ? (
                                    <p
                                        className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                        role="alert"
                                    >
                                        {errors.bill}
                                    </p>
                                ) : null}

                                <FormField
                                    error={errors.payment_method}
                                    hint="This records the method only; AfyaScope does not contact an external gateway."
                                    id="payment-method"
                                    label="Payment method"
                                    required
                                >
                                    <select
                                        aria-describedby={
                                            errors.payment_method
                                                ? 'payment-method-error'
                                                : 'payment-method-hint'
                                        }
                                        aria-invalid={Boolean(
                                            errors.payment_method,
                                        )}
                                        className={formControlStyles}
                                        defaultValue=""
                                        id="payment-method"
                                        name="payment_method"
                                        required
                                    >
                                        <option value="">
                                            Select a payment method
                                        </option>
                                        {paymentMethods.map((method) => (
                                            <option
                                                key={method.value}
                                                value={method.value}
                                            >
                                                {method.label}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>

                                <div className="rounded-control border border-warning-border bg-warning-soft px-4 py-3 text-sm leading-6 text-warning">
                                    Recording payment will create one immutable
                                    Payment and issue one Receipt. Financial
                                    clearance is not granted by this action.
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
                                            ? 'Recording…'
                                            : 'Record Payment & Issue Receipt'}
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

CreatePayment.layout = [AuthenticatedLayout];
