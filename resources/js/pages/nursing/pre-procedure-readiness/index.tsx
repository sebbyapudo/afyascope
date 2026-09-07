import { Form, Head } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, show, store } from '@/routes/nursing/pre-procedure-readiness';
import type { PreProcedureReadinessQueue } from '@/types';

type PreProcedureReadinessIndexProps = {
    preparations: PreProcedureReadinessQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function PreProcedureReadinessIndex({
    preparations,
}: PreProcedureReadinessIndexProps) {
    const { data, pagination } = preparations;

    return (
        <>
            <Head title="Procedure preparation" />
            <PageContainer width="wide">
                <PageHeader
                    description="Verify consent and clinical readiness after procedure financial clearance. Procedure decisions remain Doctor-owned."
                    title="Procedure preparation"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Procedure-required Visits appear here after their procedure financial clearance and leave after Nurse readiness is completed."
                            title="No patients awaiting preparation"
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
                                            Financially cleared
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Preparation
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
                                            <td className="px-5 py-4 font-semibold text-brand-primary tabular-nums">
                                                {item.visit.visitNumber}
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
                                            <td className="px-5 py-4 text-text-secondary">
                                                {item.doctor.name}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(
                                                    item.procedureClearedAt,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                {item.readiness ? (
                                                    <div className="grid gap-1">
                                                        <StatusBadge tone="info">
                                                            {
                                                                item.readiness
                                                                    .status
                                                                    .label
                                                            }
                                                        </StatusBadge>
                                                        <span className="text-xs text-text-secondary">
                                                            Nurse{' '}
                                                            {
                                                                item.readiness
                                                                    .nurse.name
                                                            }
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <StatusBadge tone="warning">
                                                        Awaiting preparation
                                                    </StatusBadge>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                {item.readiness ? (
                                                    <ActionLink
                                                        href={show(
                                                            item.readiness.id,
                                                        )}
                                                        size="small"
                                                        variant={
                                                            item.readiness
                                                                .canManage
                                                                ? 'primary'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {item.readiness
                                                            .canManage
                                                            ? 'Continue preparation'
                                                            : 'View preparation'}
                                                    </ActionLink>
                                                ) : (
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
                                                                    : 'Start preparation'}
                                                            </Button>
                                                        )}
                                                    </Form>
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
                                Showing {pagination.from}–{pagination.to} of{' '}
                                {pagination.total}
                            </p>
                            <nav
                                aria-label="Procedure preparation queue pagination"
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

PreProcedureReadinessIndex.layout = [AuthenticatedLayout];
