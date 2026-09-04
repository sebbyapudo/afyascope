import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { create, index } from '@/routes/billing/procedures';
import type { ProcedureBillingQueue } from '@/types';

type ProcedureBillingIndexProps = {
    handoffs: ProcedureBillingQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ProcedureBillingIndex({
    handoffs,
}: ProcedureBillingIndexProps) {
    const { data, pagination } = handoffs;

    return (
        <>
            <Head title="Procedure billing" />
            <PageContainer width="wide">
                <PageHeader
                    description="Create a procedure Bill only from the Doctor's authoritative procedure decision and billing handoff."
                    title="Procedure billing queue"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Procedure-required decisions leave this queue when their procedure Bill is created."
                            title="No procedures awaiting billing"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-5xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Handoff
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Visit
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Procedure
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
                                    {data.map((handoff) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={handoff.id}
                                        >
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {handoff.handoffNumber}
                                                <span className="mt-1 block text-xs font-normal text-text-secondary">
                                                    {formatDateTime(
                                                        handoff.decidedAt,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {handoff.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {
                                                        handoff.patient
                                                            .patientNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary tabular-nums">
                                                {handoff.visit.visitNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {handoff.procedure.name}
                                                </p>
                                                <p className="mt-1 text-text-secondary tabular-nums">
                                                    {formatMinorAmount(
                                                        handoff.procedure
                                                            .amountMinor,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="warning">
                                                    Awaiting billing
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={create(handoff.id)}
                                                    size="small"
                                                >
                                                    Create Bill
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
                                aria-label="Procedure billing queue pagination"
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

ProcedureBillingIndex.layout = [AuthenticatedLayout];
