import { Form, Head } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, show, store } from '@/routes/clinical/procedures';
import type { InProgressProcedureQueue, ReadyProcedureQueue } from '@/types';

type ProcedureIndexProps = {
    readyProcedures: ReadyProcedureQueue;
    inProgressProcedures: InProgressProcedureQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ProcedureIndex({
    readyProcedures,
    inProgressProcedures,
}: ProcedureIndexProps) {
    return (
        <>
            <Head title="Doctor procedures" />
            <PageContainer width="wide">
                <PageHeader
                    description="Procedures released by completed Nurse readiness and your active procedure documentation."
                    title="Doctor procedures"
                />

                <Panel className="overflow-hidden">
                    <header className="border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">
                            Ready for Doctor procedure
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Completed Nurse readiness is the authoritative
                            clinical handoff. Oldest ready patient is shown
                            first.
                        </p>
                    </header>

                    {readyProcedures.data.length === 0 ? (
                        <EmptyState
                            description="A procedure appears here only after the responsible Nurse completes readiness."
                            title="No procedures are ready"
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
                                            Selected procedure
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Nurse readiness
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
                                    {readyProcedures.data.map((item) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={item.visit.id}
                                        >
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {item.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {item.patient.patientNumber}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-semibold text-brand-primary tabular-nums">
                                                    {item.visit.visitNumber}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    {formatDateTime(
                                                        item.visit.occurredAt,
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {item.procedure.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {
                                                        item.procedure
                                                            .decisionNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="success">
                                                    Ready
                                                </StatusBadge>
                                                <p className="mt-2 text-xs text-text-secondary">
                                                    Nurse{' '}
                                                    {item.readiness.nurse.name}
                                                </p>
                                                {item.readiness.completedAt ? (
                                                    <p className="mt-1 text-xs text-text-secondary">
                                                        {formatDateTime(
                                                            item.readiness
                                                                .completedAt,
                                                        )}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Form
                                                    {...store.form(
                                                        item.visit.id,
                                                    )}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            disabled={
                                                                processing
                                                            }
                                                            size="small"
                                                            type="submit"
                                                        >
                                                            {processing
                                                                ? 'Starting…'
                                                                : 'Start procedure'}
                                                        </Button>
                                                    )}
                                                </Form>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {readyProcedures.pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {readyProcedures.pagination.from}–
                                {readyProcedures.pagination.to} of{' '}
                                {readyProcedures.pagination.total}
                            </p>
                            <nav
                                aria-label="Ready procedure queue pagination"
                                className="flex items-center gap-2"
                            >
                                {readyProcedures.pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressProcedures
                                                        .pagination.currentPage,
                                                ready_page:
                                                    readyProcedures.pagination
                                                        .currentPage - 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {readyProcedures.pagination.currentPage <
                                readyProcedures.pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressProcedures
                                                        .pagination.currentPage,
                                                ready_page:
                                                    readyProcedures.pagination
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
                    <header className="border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">
                            My procedures in progress
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Only procedures owned by you are actionable here.
                        </p>
                    </header>

                    {inProgressProcedures.data.length === 0 ? (
                        <EmptyState
                            description="A procedure appears here after you start it from the ready queue."
                            title="No procedures in progress"
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
                                    {inProgressProcedures.data.map((item) => (
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
                                            <td className="px-5 py-4">
                                                <p className="font-semibold text-brand-primary tabular-nums">
                                                    {item.procedureNumber}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    {item.procedure.name}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(item.startedAt)}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="info">
                                                    {item.status.label}
                                                </StatusBadge>
                                                <p className="mt-2 text-xs text-text-secondary">
                                                    {item.visit.nextStep}
                                                </p>
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

                    {inProgressProcedures.pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {inProgressProcedures.pagination.from}–
                                {inProgressProcedures.pagination.to} of{' '}
                                {inProgressProcedures.pagination.total}
                            </p>
                            <nav
                                aria-label="In-progress procedure pagination"
                                className="flex items-center gap-2"
                            >
                                {inProgressProcedures.pagination.currentPage >
                                1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressProcedures
                                                        .pagination
                                                        .currentPage - 1,
                                                ready_page:
                                                    readyProcedures.pagination
                                                        .currentPage,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {inProgressProcedures.pagination.currentPage <
                                inProgressProcedures.pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressProcedures
                                                        .pagination
                                                        .currentPage + 1,
                                                ready_page:
                                                    readyProcedures.pagination
                                                        .currentPage,
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

ProcedureIndex.layout = [AuthenticatedLayout];
