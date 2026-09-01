import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { create, index } from '@/routes/check-ins';
import type { CheckInQueue } from '@/types';

type CheckInIndexProps = { visits: CheckInQueue };

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function CheckInIndex({ visits }: CheckInIndexProps) {
    const { data, pagination } = visits;

    return (
        <>
            <Head title="Reception check-in" />
            <PageContainer width="wide">
                <PageHeader
                    description="Consultation-cleared Visits awaiting the Reception handoff to Doctor consultation."
                    title="Reception check-in"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Visits leave this queue after Reception check-in is completed."
                            title="No Visits awaiting check-in"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Visit
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Clearance
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
                                    {data.map((visit) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={visit.id}
                                        >
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
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {visit.visitNumber}
                                                <span className="mt-1 block text-xs font-normal text-text-secondary">
                                                    {formatDateTime(
                                                        visit.occurredAt,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary tabular-nums">
                                                {
                                                    visit.clearance
                                                        .clearanceNumber
                                                }
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="success">
                                                    Financially cleared
                                                </StatusBadge>
                                                <p className="mt-2 text-xs text-text-secondary">
                                                    {visit.nextStep}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={create(visit.id)}
                                                    size="small"
                                                >
                                                    Review Check-in
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
                                aria-label="Reception check-in queue pagination"
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

CheckInIndex.layout = [AuthenticatedLayout];
