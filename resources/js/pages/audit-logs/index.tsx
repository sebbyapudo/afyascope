import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes';
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
        return <span className="text-slate-500">Event recorded</span>;
    }

    return (
        <dl className="grid gap-2">
            {changes.map((change) => (
                <div key={change.field}>
                    <dt className="font-medium text-slate-800">
                        {change.label}
                    </dt>
                    <dd className="mt-0.5 text-xs text-slate-600">
                        <span>
                            {formatAuditValue(change.field, change.before)}
                        </span>
                        <span
                            aria-hidden="true"
                            className="px-2 text-slate-400"
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
            <main className="min-h-screen bg-slate-50 p-4 sm:p-8">
                <div className="mx-auto grid max-w-7xl gap-6">
                    <header>
                        <Link
                            className="text-sm font-medium text-sky-800 underline-offset-4 hover:underline"
                            href={dashboard()}
                        >
                            Back to dashboard
                        </Link>
                        <h1 className="mt-3 text-3xl font-semibold text-slate-900">
                            Audit history
                        </h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Read-only history of important administrative
                            changes.
                        </p>
                    </header>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        {data.length === 0 ? (
                            <p className="px-6 py-12 text-center text-sm text-slate-600">
                                No audit events have been recorded yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-5xl border-collapse text-left text-sm">
                                    <thead className="bg-slate-100 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                        <tr>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Time
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Actor
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Action
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Affected record
                                            </th>
                                            <th
                                                className="px-5 py-4"
                                                scope="col"
                                            >
                                                Changes
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-200">
                                        {data.map((auditLog) => (
                                            <tr key={auditLog.id}>
                                                <td className="px-5 py-4 whitespace-nowrap text-slate-600">
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
                                                    <p className="font-medium text-slate-900">
                                                        {auditLog.actor?.name ??
                                                            'System / bootstrap'}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {auditLog.actor
                                                            ?.email ??
                                                            'No authenticated actor'}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4 text-slate-700">
                                                    {auditLog.action.label}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p className="font-medium text-slate-900">
                                                        {auditLog.subject.label}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {auditLog.subject.type}{' '}
                                                        #{auditLog.subject.id}
                                                    </p>
                                                </td>
                                                <td className="min-w-72 px-5 py-4">
                                                    <ChangeSummary
                                                        changes={
                                                            auditLog.changes
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {pagination.total > 0 ? (
                            <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-4 text-sm text-slate-600">
                                <p>
                                    Showing {pagination.from}–{pagination.to} of{' '}
                                    {pagination.total}
                                </p>
                                <nav
                                    aria-label="Audit history pagination"
                                    className="flex items-center gap-2"
                                >
                                    {pagination.currentPage > 1 ? (
                                        <Link
                                            className="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800"
                                            href={index({
                                                query: {
                                                    page:
                                                        pagination.currentPage -
                                                        1,
                                                },
                                            })}
                                        >
                                            Previous
                                        </Link>
                                    ) : null}
                                    {pagination.currentPage <
                                    pagination.lastPage ? (
                                        <Link
                                            className="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white px-4 font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800"
                                            href={index({
                                                query: {
                                                    page:
                                                        pagination.currentPage +
                                                        1,
                                                },
                                            })}
                                        >
                                            Next
                                        </Link>
                                    ) : null}
                                </nav>
                            </footer>
                        ) : null}
                    </section>
                </div>
            </main>
        </>
    );
}
