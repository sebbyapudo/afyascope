import { Head, Link } from '@inertiajs/react';
import { ServiceCatalogItemForm } from '@/components/service-catalog/service-catalog-item-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, store } from '@/routes/service-catalog';
import type { ServiceCategoryOption } from '@/types';

type CreateServiceProps = {
    categories: ServiceCategoryOption[];
};

export default function CreateService({ categories }: CreateServiceProps) {
    return (
        <>
            <Head title="Add Service Catalog" />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to service catalog
                        </Link>
                    }
                    description="Add a consultation or procedure service and its current price. New services start active."
                    title="Add service"
                />
                <Panel className="p-5 sm:p-8">
                    <ServiceCatalogItemForm
                        categories={categories}
                        form={store.form()}
                        submitLabel="Add service"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

CreateService.layout = [AuthenticatedLayout];
