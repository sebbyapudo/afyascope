import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { create, index } from '@/routes/nursing/recovery';
import type { RecoveryQueue } from '@/types';

type RecoveryIndexProps = {
    procedures: RecoveryQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function RecoveryIndex({ procedures }: RecoveryIndexProps) {
    const { data, pagination } = procedures;

    return (
        <>
            <Head title="Nursing recovery" />
            <PageContainer width="wide">
                <PageHeader
                    description="Start the durable Nursing recovery episode after the responsible Doctor completes the procedure."
                    title="Nursing recovery"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Completed procedures appear here until a Nurse explicitly starts recovery."
                            title="No patients awaiting recovery"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-5xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
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
                                            Responsible Doctor
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Completed
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
                                    {data.map((item) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={item.id}
                                        >
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {item.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {item.patient.patientNumber}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {item.visit.visitNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {item.procedure.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {item.procedureNumber}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {item.doctor.name}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {item.completedAt
                                                    ? formatDateTime(
                                                          item.completedAt,
                                                      )
                                                    : 'Not recorded'}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="warning">
                                                    Ready for Nursing recovery
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={create(item.id)}
                                                    size="small"
                                                >
                                                    View and start
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
                                aria-label="Nursing recovery queue pagination"
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

RecoveryIndex.layout = [AuthenticatedLayout];
