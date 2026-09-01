import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index, store } from '@/routes/billing/consultations';
import type { ConsultationBillingVisit, ConsultationService } from '@/types';

type CreateConsultationBillProps = {
    consultationServices: ConsultationService[];
    visit: ConsultationBillingVisit;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CreateConsultationBill({
    consultationServices,
    visit,
}: CreateConsultationBillProps) {
    return (
        <>
            <Head title={`Bill ${visit.visitNumber}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to consultation billing queue
                        </Link>
                    }
                    description="Confirm the consultation service. The service name and current price will be preserved on the Bill."
                    eyebrow={visit.visitNumber}
                    title={`Create consultation Bill for ${visit.patient.name}`}
                />

                <Panel className="p-5 sm:p-6">
                    <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {visit.patient.name}
                                <span className="mt-1 block font-normal text-text-secondary tabular-nums">
                                    {visit.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit occurred
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {formatDateTime(visit.occurredAt)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="info">
                                    {visit.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                {consultationServices.length === 0 ? (
                    <Panel className="overflow-hidden">
                        <EmptyState
                            description="An active consultation-category service and price must be configured before this Visit can be billed."
                            title="Consultation billing is not configured"
                        />
                    </Panel>
                ) : (
                    <Panel className="p-5 sm:p-8">
                        <Form {...store.form(visit.id)}>
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
                                    <FormField
                                        error={errors.service_catalog_item_id}
                                        hint="Only active consultation services are available."
                                        id="consultation-service"
                                        label="Consultation service"
                                        required
                                    >
                                        <select
                                            aria-describedby={
                                                errors.service_catalog_item_id
                                                    ? 'consultation-service-error'
                                                    : 'consultation-service-hint'
                                            }
                                            aria-invalid={Boolean(
                                                errors.service_catalog_item_id,
                                            )}
                                            className={formControlStyles}
                                            defaultValue={
                                                consultationServices.length ===
                                                1
                                                    ? consultationServices[0].id
                                                    : ''
                                            }
                                            id="consultation-service"
                                            name="service_catalog_item_id"
                                            required
                                        >
                                            {consultationServices.length > 1 ? (
                                                <option value="">
                                                    Select a consultation
                                                    service
                                                </option>
                                            ) : null}
                                            {consultationServices.map(
                                                (service) => (
                                                    <option
                                                        key={service.id}
                                                        value={service.id}
                                                    >
                                                        {service.name} â€”{' '}
                                                        {formatMinorAmount(
                                                            service.unitPriceMinor,
                                                        )}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </FormField>

                                    <div className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm leading-6 text-info">
                                        After creation, this Visit will remain
                                        Created and move to â€œAwaiting
                                        consultation paymentâ€.
                                    </div>

                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Creating Billâ€¦'
                                                : 'Create consultation Bill'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </Panel>
                )}
            </PageContainer>
        </>
    );
}

CreateConsultationBill.layout = [AuthenticatedLayout];
