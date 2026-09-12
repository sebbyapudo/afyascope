import { Form, Head, Link } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index, show } from '@/routes/audit-logs';
import type {
    AuditChange,
    AuditFilterOption,
    AuditLogFilters,
    AuditLogPage,
    AuditValue,
} from '@/types';

type AuditLogIndexProps = {
    auditLogs: AuditLogPage;
    events: AuditFilterOption[];
    filters: AuditLogFilters;
    subjectTypes: AuditFilterOption[];
};

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatAuditValue(field: string, value: AuditValue): string {
    if (value === null) {
        return 'Not set';
    }

    if (field === 'is_active' && typeof value === 'boolean') {
        return value ? 'Active' : 'Disabled';
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (
        (field === 'amount_minor' || field === 'unit_price_minor') &&
        typeof value === 'number'
    ) {
        return `KES ${formatMinorAmount(value)}`;
    }

    if (
        typeof value === 'string' &&
        (field.endsWith('_at') || field === 'scheduled_at')
    ) {
        return formatDateTime(value);
    }

    return String(value).replaceAll('_', ' ');
}

function changeSummary(changes: AuditChange[]): string {
    if (changes.length === 0) {
        return 'Significant event recorded';
    }

    return changes
        .slice(0, 2)
        .map((change) => {
            const before = formatAuditValue(change.field, change.before);
            const after = formatAuditValue(change.field, change.after);

            if (change.before === null) {
                return `${change.label}: ${after}`;
            }

            if (change.after === null) {
                return `${change.label}: removed`;
            }

            return `${change.label}: ${before} → ${after}`;
        })
        .join(' · ');
}

export default function AuditLogIndex({
    auditLogs,
    events,
    filters,
    subjectTypes,
}: AuditLogIndexProps) {
    const { data, pagination } = auditLogs;
    const hasFilters = Boolean(
        filters.q ||
        filters.event ||
        filters.actor ||
        filters.subjectType ||
        filters.subjectReference ||
        filters.dateFrom ||
        filters.dateTo,
    );
    const paginationQuery = {
        q: filters.q,
        event: filters.event,
        actor: filters.actor,
        subject_type: filters.subjectType,
        subject_reference: filters.subjectReference,
        date_from: filters.dateFrom,
        date_to: filters.dateTo,
    };

    return (
        <>
            <Head title="Audit Log" />
            <PageContainer width="wide">
                <PageHeader
                    description="Review immutable records of significant administrative, financial, and care-workflow events."
                    title="Audit Log"
                />

                <Panel className="p-5 sm:p-6">
                    <Form {...index.form()}>
                        {({ processing }) => (
                            <div className="grid gap-4">
                                <div className="grid gap-4 lg:grid-cols-3">
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-q"
                                        >
                                            General search
                                        </label>
                                        <input
                                            className={formControlStyles}
                                            defaultValue={filters.q}
                                            id="audit-q"
                                            name="q"
                                            placeholder="Event, actor, or reference"
                                            type="search"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-event"
                                        >
                                            Event
                                        </label>
                                        <select
                                            className={formControlStyles}
                                            defaultValue={filters.event ?? ''}
                                            id="audit-event"
                                            name="event"
                                        >
                                            <option value="">All events</option>
                                            {events.map((event) => (
                                                <option
                                                    key={event.value}
                                                    value={event.value}
                                                >
                                                    {event.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-actor"
                                        >
                                            Actor
                                        </label>
                                        <input
                                            className={formControlStyles}
                                            defaultValue={filters.actor}
                                            id="audit-actor"
                                            name="actor"
                                            placeholder="Name or email"
                                            type="search"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-[14rem_minmax(0,1fr)_11rem_11rem_auto] lg:items-end">
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-subject-type"
                                        >
                                            Record type
                                        </label>
                                        <select
                                            className={formControlStyles}
                                            defaultValue={
                                                filters.subjectType ?? ''
                                            }
                                            id="audit-subject-type"
                                            name="subject_type"
                                        >
                                            <option value="">
                                                All record types
                                            </option>
                                            {subjectTypes.map((subjectType) => (
                                                <option
                                                    key={subjectType.value}
                                                    value={subjectType.value}
                                                >
                                                    {subjectType.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-subject-reference"
                                        >
                                            Record reference
                                        </label>
                                        <input
                                            className={formControlStyles}
                                            defaultValue={
                                                filters.subjectReference
                                            }
                                            id="audit-subject-reference"
                                            name="subject_reference"
                                            placeholder="VIS-000001, BILL-000001…"
                                            type="search"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-date-from"
                                        >
                                            From date
                                        </label>
                                        <input
                                            className={formControlStyles}
                                            defaultValue={
                                                filters.dateFrom ?? ''
                                            }
                                            id="audit-date-from"
                                            name="date_from"
                                            type="date"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            className="text-sm font-medium text-text"
                                            htmlFor="audit-date-to"
                                        >
                                            To date
                                        </label>
                                        <input
                                            className={formControlStyles}
                                            defaultValue={filters.dateTo ?? ''}
                                            id="audit-date-to"
                                            name="date_to"
                                            type="date"
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            Filter
                                        </Button>
                                        <ActionLink
                                            href={index()}
                                            variant="secondary"
                                        >
                                            Clear
                                        </ActionLink>
                                    </div>
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            action={
                                hasFilters ? (
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear filters
                                    </ActionLink>
                                ) : undefined
                            }
                            description={
                                hasFilters
                                    ? 'No audit events match the current filters.'
                                    : 'Significant business and system events will appear here after they are recorded.'
                            }
                            title="No audit events found"
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
                                            Event
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Actor
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Record
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Safe context
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
                                    {data.map((auditLog) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={auditLog.id}
                                        >
                                            <td className="px-5 py-4 whitespace-nowrap text-text-secondary tabular-nums">
                                                {formatDateTime(
                                                    auditLog.occurredAt,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {auditLog.action.label}
                                                </p>
                                                <p className="mt-1 font-mono text-xs text-text-muted">
                                                    {auditLog.action.value}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {auditLog.actor?.name ??
                                                        'System / bootstrap'}
                                                </p>
                                                {auditLog.actor &&
                                                !auditLog.actor.isActive ? (
                                                    <StatusBadge className="mt-1">
                                                        Account disabled
                                                    </StatusBadge>
                                                ) : null}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {auditLog.subject
                                                        .reference ??
                                                        `${auditLog.subject.type} record #${auditLog.subject.internalId}`}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    {auditLog.subject.type}
                                                </p>
                                            </td>
                                            <td className="max-w-sm px-5 py-4 text-text-secondary">
                                                {changeSummary(
                                                    auditLog.changes,
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={show(auditLog.id)}
                                                >
                                                    Review
                                                </Link>
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
                                aria-label="Audit Log pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                ...paginationQuery,
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
                                                ...paginationQuery,
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
