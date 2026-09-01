import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { create, index } from '@/routes/billing/clearances';
import { show as receiptShow } from '@/routes/billing/receipts';
import type { FinancialClearanceQueue } from '@/types';

type FinancialClearanceIndexProps = {
    bills: FinancialClearanceQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function FinancialClearanceIndex({
    bills,
}: FinancialClearanceIndexProps) {
    const { data, pagination } = bills;

    return (
        <>
            <Head title="Consultation financial clearance" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review fully paid consultation Bills and grant the separate financial clearance required before Reception check-in."
                    title="Consultation financial clearance"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Paid consultation Bills leave this queue after financial clearance is granted."
                            title="No consultation Bills awaiting clearance"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-5xl text-left text-sm">
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
                                            Paid
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Receipt
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            State
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
                                                <span className="mt-1 block text-xs font-normal text-text-secondary">
                                                    Paid{' '}
                                                    {formatDateTime(
                                                        bill.payment.recordedAt,
                                                    )}
                                                </span>
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
                                                    bill.payment.amountMinor,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <ActionLink
                                                    href={receiptShow(
                                                        bill.payment.receipt.id,
                                                    )}
                                                    size="small"
                                                    variant="secondary"
                                                >
                                                    {
                                                        bill.payment.receipt
                                                            .receiptNumber
                                                    }
                                                </ActionLink>
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="warning">
                                                    Awaiting clearance
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={create(bill.id)}
                                                    size="small"
                                                >
                                                    Review Clearance
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
                                aria-label="Consultation financial clearance queue pagination"
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

FinancialClearanceIndex.layout = [AuthenticatedLayout];
