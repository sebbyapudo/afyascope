import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { create, index, show } from '@/routes/nursing/recovery';
import type { ActiveRecoveryQueue, RecoveryQueue } from '@/types';

type RecoveryIndexProps = {
    activeRecoveries: ActiveRecoveryQueue;
    awaitingRecoveries: RecoveryQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function RecoveryIndex({
    activeRecoveries,
    awaitingRecoveries,
}: RecoveryIndexProps) {
    const { data, pagination } = awaitingRecoveries;

    return (
        <>
            <Head title="Nursing recovery" />
            <PageContainer width="wide">
                <PageHeader
                    description="Start the durable Nursing recovery episode after the responsible Doctor completes the procedure."
                    title="Nursing recovery"
                />

                <Panel className="overflow-hidden">
                    <div className="border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">
                            My active recoveries
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            In-progress episodes for which you are the
                            responsible Nurse.
                        </p>
                    </div>
                    {activeRecoveries.data.length === 0 ? (
                        <EmptyState
                            description="Recoveries you start remain here while monitoring continues."
                            title="No active recoveries"
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
                                            Recovery
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Procedure
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Started
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
                                    {activeRecoveries.data.map((item) => (
                                        <tr
                                            className="hover:bg-canvas"
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
                                                {item.recoveryNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="text-text">
                                                    {item.procedure.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {
                                                        item.procedure
                                                            .procedureNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(item.startedAt)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge
                                                    tone={
                                                        item.visit.nextStep ===
                                                        'Doctor review required'
                                                            ? 'warning'
                                                            : 'info'
                                                    }
                                                >
                                                    {item.visit.nextStep}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={show(item.id)}
                                                    size="small"
                                                >
                                                    Open workspace
                                                </ActionLink>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {activeRecoveries.pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {activeRecoveries.pagination.from}–
                                {activeRecoveries.pagination.to} of{' '}
                                {activeRecoveries.pagination.total}
                            </p>
                            <nav
                                aria-label="Active recoveries pagination"
                                className="flex items-center gap-2"
                            >
                                {activeRecoveries.pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                active_page:
                                                    activeRecoveries.pagination
                                                        .currentPage - 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {activeRecoveries.pagination.currentPage <
                                activeRecoveries.pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                active_page:
                                                    activeRecoveries.pagination
                                                        .currentPage + 1,
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

                <Panel className="overflow-hidden">
                    <div className="border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">
                            Awaiting recovery
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Completed procedures without a Recovery Episode.
                        </p>
                    </div>
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
                                                awaiting_page:
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
                                                awaiting_page:
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
