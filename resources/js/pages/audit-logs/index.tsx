import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index } from '@/routes/audit-logs';
import type { AuditChange, AuditLogPage, AuditValue } from '@/types';

type AuditLogIndexProps = {
    auditLogs: AuditLogPage;
};

function formatAuditValue(field: string, value: AuditValue): string {
    if (value === null) {
        return 'Not set';
    }

    if (field === 'is_active' && typeof value === 'boolean') {
        return value ? 'Active' : 'Disabled';
    }

    if (typeof value === 'object' && !Array.isArray(value)) {
        const displayName = value.name;

        if (typeof displayName === 'string') {
            return displayName;
        }

        return Object.values(value).map(String).join(', ');
    }

    if (Array.isArray(value)) {
        return value.map(String).join(', ');
    }

    return String(value);
}

function ChangeSummary({ changes }: { changes: AuditChange[] }) {
    if (changes.length === 0) {
        return <span className="text-text-secondary">Event recorded</span>;
    }

    return (
        <dl className="grid gap-2">
            {changes.map((change) => (
                <div key={change.field}>
                    <dt className="font-medium text-text">{change.label}</dt>
                    <dd className="mt-0.5 text-xs text-text-secondary">
                        <span>
                            {formatAuditValue(change.field, change.before)}
                        </span>
                        <span
                            aria-hidden="true"
                            className="px-2 text-text-muted"
                        >
                            →
                        </span>
                        <span>
                            {formatAuditValue(change.field, change.after)}
                        </span>
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export default function AuditLogIndex({ auditLogs }: AuditLogIndexProps) {
    const { data, pagination } = auditLogs;

    return (
        <>
            <Head title="Audit history" />
            <PageContainer width="wide">
                <PageHeader
                    description="Read-only history of important administrative changes."
                    title="Audit history"
                />

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            description="Important administrative changes will appear here after they are recorded."
                            title="No audit events recorded"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-5xl border-collapse text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Time
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Actor
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Action
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Affected record
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Changes
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.map((auditLog) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={auditLog.id}
                                        >
                                            <td className="px-5 py-4 whitespace-nowrap text-text-secondary tabular-nums">
                                                {new Intl.DateTimeFormat(
                                                    undefined,
                                                    {
                                                        dateStyle: 'medium',
                                                        timeStyle: 'short',
                                                    },
                                                ).format(
                                                    new Date(
                                                        auditLog.occurredAt,
                                                    ),
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {auditLog.actor?.name ??
                                                        'System / bootstrap'}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    {auditLog.actor?.email ??
                                                        'No authenticated actor'}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text">
                                                {auditLog.action.label}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {auditLog.subject.label}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    {auditLog.subject.type} #
                                                    {auditLog.subject.id}
                                                </p>
                                            </td>
                                            <td className="min-w-72 px-5 py-4">
                                                <ChangeSummary
                                                    changes={auditLog.changes}
                                                />
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
                                aria-label="Audit history pagination"
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

AuditLogIndex.layout = [AuthenticatedLayout];
