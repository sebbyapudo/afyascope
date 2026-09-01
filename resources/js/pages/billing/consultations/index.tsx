import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { create, index } from '@/routes/billing/consultations';
import type { ConsultationBillingQueue, ConsultationService } from '@/types';

type ConsultationBillingIndexProps = {
    consultationServices: ConsultationService[];
    visits: ConsultationBillingQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ConsultationBillingIndex({
    consultationServices,
    visits,
}: ConsultationBillingIndexProps) {
    const { data, pagination } = visits;
    const hasServices = consultationServices.length > 0;

    return (
        <>
            <Head title="Consultation billing" />
            <PageContainer width="wide">
                <PageHeader
                    description="Create the consultation Bill for each newly created Visit. Payment and financial clearance remain separate later steps."
                    title="Consultation billing queue"
                />

                <Panel className="overflow-hidden">
                    {hasServices ? (
                        <div className="p-5 sm:p-6">
                            <h2 className="text-lg font-semibold text-text">
                                Active consultation services
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                The selected service and price will be
                                snapshotted onto the Bill.
                            </p>
                            <ul className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {consultationServices.map((service) => (
                                    <li
                                        className="rounded-control border border-border bg-surface-subtle px-4 py-3"
                                        key={service.id}
                                    >
                                        <p className="text-sm font-semibold text-text">
                                            {service.name}
                                        </p>
                                        <p className="mt-1 text-sm text-text-secondary tabular-nums">
                                            {formatMinorAmount(
                                                service.unitPriceMinor,
                                            )}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <EmptyState
                            description="An active consultation-category service and price must be configured before the Accountant can create a Bill."
                            title="No consultation service configured"
                        />
                    )}
                </Panel>

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Visits leave this queue as soon as their consultation Bill is created."
                            title="No Visits awaiting consultation billing"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Visit
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Occurred
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Status
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Consultation service
                                        </th>
                                        <th
                                            className="px-5 py-4 text-right"
                                            scope="col"
                                        >
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.map((visit) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={visit.id}
                                        >
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {visit.visitNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {visit.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {
                                                        visit.patient
                                                            .patientNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(
                                                    visit.occurredAt,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="info">
                                                    {visit.status.label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {consultationServices.length ===
                                                1 ? (
                                                    <>
                                                        <span className="block font-medium text-text">
                                                            {
                                                                consultationServices[0]
                                                                    .name
                                                            }
                                                        </span>
                                                        <span className="mt-1 block tabular-nums">
                                                            {formatMinorAmount(
                                                                consultationServices[0]
                                                                    .unitPriceMinor,
                                                            )}
                                                        </span>
                                                    </>
                                                ) : hasServices ? (
                                                    `${consultationServices.length} active options`
                                                ) : (
                                                    'Configuration required'
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                {hasServices ? (
                                                    <ActionLink
                                                        href={create(visit.id)}
                                                        size="small"
                                                    >
                                                        Create Bill
                                                    </ActionLink>
                                                ) : (
                                                    <span className="text-xs font-medium text-text-secondary">
                                                        Billing unavailable
                                                    </span>
                                                )}
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
                                Showing {pagination.from}â€“{pagination.to} of{' '}
                                {pagination.total}
                            </p>
                            <nav
                                aria-label="Consultation billing queue pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
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

ConsultationBillingIndex.layout = [AuthenticatedLayout];
