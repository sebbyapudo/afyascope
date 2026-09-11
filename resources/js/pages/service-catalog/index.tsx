import { Form, Head, Link } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { create, edit, index, show } from '@/routes/service-catalog';
import { update as updateStatus } from '@/routes/service-catalog/status';
import type { ServiceCatalogPage, ServiceCategoryOption } from '@/types';

type CatalogIndexProps = {
    categories: ServiceCategoryOption[];
    filters: {
        q: string;
        category: string | null;
        status: string | null;
    };
    services: ServiceCatalogPage;
    status?: string | null;
};

export default function CatalogIndex({
    categories,
    filters,
    services,
    status: flashStatus,
}: CatalogIndexProps) {
    const { data, pagination } = services;

    return (
        <>
            <Head title="Service Catalog" />
            <PageContainer width="wide">
                <PageHeader
                    actions={
                        <ActionLink href={create()}>Add service</ActionLink>
                    }
                    description="Configure the consultation and procedure services available to operational workflows."
                    title="Service & procedure catalog"
                />

                {flashStatus ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {flashStatus}
                    </p>
                ) : null}

                <Panel className="p-5 sm:p-6">
                    <Form {...index.form()}>
                        {({ processing }) => (
                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_13rem_13rem_auto] lg:items-end">
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="catalog-q"
                                    >
                                        Search services
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.q}
                                        id="catalog-q"
                                        name="q"
                                        placeholder="Service name"
                                        type="search"
                                    />
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="catalog-category"
                                    >
                                        Category
                                    </label>
                                    <select
                                        className={formControlStyles}
                                        defaultValue={filters.category ?? ''}
                                        id="catalog-category"
                                        name="category"
                                    >
                                        <option value="">All categories</option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.value}
                                                value={category.value}
                                            >
                                                {category.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="catalog-status"
                                    >
                                        Status
                                    </label>
                                    <select
                                        className={formControlStyles}
                                        defaultValue={filters.status ?? ''}
                                        id="catalog-status"
                                        name="status"
                                    >
                                        <option value="">All statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Inactive
                                        </option>
                                    </select>
                                </div>
                                <div className="flex gap-2">
                                    <Button disabled={processing} type="submit">
                                        Filter
                                    </Button>
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear
                                    </ActionLink>
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            action={
                                filters.q ||
                                filters.category ||
                                filters.status ? (
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear filters
                                    </ActionLink>
                                ) : (
                                    <ActionLink href={create()}>
                                        Add service
                                    </ActionLink>
                                )
                            }
                            description="No catalog services match the current view."
                            title="No services found"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Service
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Category
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Current price (KES)
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Status
                                        </th>
                                        <th
                                            className="px-5 py-4 text-right"
                                            scope="col"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.map((service) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={service.id}
                                        >
                                            <td className="px-5 py-4">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={show(service.id)}
                                                >
                                                    {service.name}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {service.category.label}
                                            </td>
                                            <td className="px-5 py-4 font-medium text-text tabular-nums">
                                                {formatMinorAmount(
                                                    service.unitPriceMinor,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge
                                                    tone={
                                                        service.isActive
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {service.isActive
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-3">
                                                    <Link
                                                        className={
                                                            textLinkStyles
                                                        }
                                                        href={edit(service.id)}
                                                    >
                                                        Edit
                                                    </Link>
                                                    <Form
                                                        {...updateStatus.form(
                                                            service.id,
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <>
                                                                <input
                                                                    name="is_active"
                                                                    type="hidden"
                                                                    value={
                                                                        service.isActive
                                                                            ? '0'
                                                                            : '1'
                                                                    }
                                                                />
                                                                <button
                                                                    className={
                                                                        textLinkStyles
                                                                    }
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                    type="submit"
                                                                >
                                                                    {service.isActive
                                                                        ? 'Deactivate'
                                                                        : 'Activate'}
                                                                </button>
                                                            </>
                                                        )}
                                                    </Form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {pagination.from}–{pagination.to} of{' '}
                                {pagination.total}
                            </p>
                            <nav
                                aria-label="Service catalog pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                ...filters,
                                                page:
                                                    pagination.currentPage - 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {pagination.currentPage <
                                pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                ...filters,
                                                page:
                                                    pagination.currentPage + 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Next
                                    </ActionLink>
                                ) : null}
                            </nav>
                        </footer>
                    ) : null}
                </Panel>
            </PageContainer>
        </>
    );
}

CatalogIndex.layout = [AuthenticatedLayout];
