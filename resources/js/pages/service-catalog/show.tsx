import { Form, Head, Link } from '@inertiajs/react';
import { ServiceCatalogItemPriceForm } from '@/components/service-catalog/service-catalog-item-price-form';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { edit, index } from '@/routes/service-catalog';
import { update as updateStatus } from '@/routes/service-catalog/status';
import type { ServiceCatalogItem } from '@/types';

type ShowServiceProps = {
    service: ServiceCatalogItem;
    status?: string | null;
};

function formatDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ShowService({
    service,
    status: flashStatus,
}: ShowServiceProps) {
    return (
        <>
            <Head title={service.name} />
            <PageContainer>
                <PageHeader
                    actions={
                        <ActionLink href={edit(service.id)}>
                            Edit service
                        </ActionLink>
                    }
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to service catalog
                        </Link>
                    }
                    description="Current catalog configuration and historical-use safeguards."
                    eyebrow={service.category.label}
                    title={service.name}
                />

                {flashStatus ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {flashStatus}
                    </p>
                ) : null}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                    <Panel className="p-5 sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold text-text">
                                    Catalog details
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Prices shown here apply only to new Bills.
                                </p>
                            </div>
                            <StatusBadge
                                tone={service.isActive ? 'success' : 'neutral'}
                            >
                                {service.isActive ? 'Active' : 'Inactive'}
                            </StatusBadge>
                        </div>
                        <dl className="mt-6 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Category
                                </dt>
                                <dd className="mt-1 font-medium text-text">
                                    {service.category.label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Current price
                                </dt>
                                <dd className="mt-1 font-medium text-text tabular-nums">
                                    KES{' '}
                                    {formatMinorAmount(service.unitPriceMinor)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Created
                                </dt>
                                <dd className="mt-1 text-text">
                                    {formatDate(service.createdAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Last updated
                                </dt>
                                <dd className="mt-1 text-text">
                                    {formatDate(service.updatedAt)}
                                </dd>
                            </div>
                        </dl>
                    </Panel>

                    <Panel className="p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-text">
                            Availability
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-text-secondary">
                            {service.isActive
                                ? 'This service is available for new eligible selections and Bills.'
                                : 'This service remains visible in history but is unavailable for new selections and Bills.'}
                        </p>
                        <Form {...updateStatus.form(service.id)}>
                            {({ processing }) => (
                                <>
                                    <input
                                        name="is_active"
                                        type="hidden"
                                        value={service.isActive ? '0' : '1'}
                                    />
                                    <Button
                                        className="mt-5 w-full"
                                        disabled={processing}
                                        type="submit"
                                        variant={
                                            service.isActive
                                                ? 'danger'
                                                : 'primary'
                                        }
                                    >
                                        {processing
                                            ? 'Saving…'
                                            : service.isActive
                                              ? 'Deactivate service'
                                              : 'Activate service'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Panel>
                </div>

                <ServiceCatalogItemPriceForm service={service} />

                <Panel className="p-5 sm:p-6">
                    <h2 className="text-lg font-semibold text-text">
                        Historical use
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-text-secondary">
                        Catalog changes never rewrite Bill-item descriptions or
                        amounts. Referenced services cannot change category and
                        are never deleted.
                    </p>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <div className="rounded-control border border-border bg-surface-subtle p-4">
                            <dt className="text-sm text-text-secondary">
                                Bill items
                            </dt>
                            <dd className="mt-1 text-xl font-semibold text-text tabular-nums">
                                {service.usage.billItems}
                            </dd>
                        </div>
                        <div className="rounded-control border border-border bg-surface-subtle p-4">
                            <dt className="text-sm text-text-secondary">
                                Procedure decisions
                            </dt>
                            <dd className="mt-1 text-xl font-semibold text-text tabular-nums">
                                {service.usage.procedureDecisions}
                            </dd>
                        </div>
                    </dl>
                </Panel>
            </PageContainer>
        </>
    );
}

ShowService.layout = [AuthenticatedLayout];
