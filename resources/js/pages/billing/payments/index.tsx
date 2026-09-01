import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { create, index } from '@/routes/billing/payments';
import type { PaymentQueue } from '@/types';

type PaymentIndexProps = {
    bills: PaymentQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function PaymentIndex({ bills }: PaymentIndexProps) {
    const { data, pagination } = bills;

    return (
        <>
            <Head title="Consultation payments" />
            <PageContainer width="wide">
                <PageHeader
                    description="Record exact payment for open consultation Bills and issue the corresponding receipt. Financial clearance remains a separate next step."
                    title="Consultation payments"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Bills leave this queue after payment and receipt creation complete successfully."
                            title="No consultation Bills awaiting payment"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Bill
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Visit
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Amount
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Status
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
                                    {data.map((bill) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={bill.id}
                                        >
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {bill.billNumber}
                                                {bill.createdAt ? (
                                                    <span className="mt-1 block text-xs font-normal text-text-secondary">
                                                        {formatDateTime(
                                                            bill.createdAt,
                                                        )}
                                                    </span>
                                                ) : null}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {bill.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {bill.patient.patientNumber}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary tabular-nums">
                                                {bill.visit.visitNumber}
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-text tabular-nums">
                                                {formatMinorAmount(
                                                    bill.totalAmountMinor,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="warning">
                                                    {bill.status.label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={create(bill.id)}
                                                    size="small"
                                                >
                                                    Record Payment
                                                </ActionLink>
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
                                aria-label="Consultation payment queue pagination"
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

PaymentIndex.layout = [AuthenticatedLayout];
