import { Head, Link } from '@inertiajs/react';
import { ServiceCatalogItemForm } from '@/components/service-catalog/service-catalog-item-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show, update } from '@/routes/service-catalog';
import type { ServiceCatalogItem, ServiceCategoryOption } from '@/types';

type EditServiceProps = {
    categories: ServiceCategoryOption[];
    service: ServiceCatalogItem;
};

export default function EditService({ categories, service }: EditServiceProps) {
    return (
        <>
            <Head title={`Edit ${service.name}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={show(service.id)}
                        >
                            Back to service details
                        </Link>
                    }
                    description="Update the service name or current price without rewriting historical transactions."
                    title={`Edit ${service.name}`}
                />
                <Panel className="p-5 sm:p-8">
                    <ServiceCatalogItemForm
                        categories={categories}
                        form={update.form(service.id)}
                        service={service}
                        submitLabel="Save changes"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

EditService.layout = [AuthenticatedLayout];
