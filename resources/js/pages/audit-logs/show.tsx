import { Head, Link } from '@inertiajs/react';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { index } from '@/routes/audit-logs';
import type { AuditLogDetail, AuditValue } from '@/types';

type AuditLogShowProps = {
    auditLog: AuditLogDetail;
};

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'long',
    }).format(new Date(value));
}

function formatValue(field: string, value: AuditValue): string {
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

export default function AuditLogShow({ auditLog }: AuditLogShowProps) {
    return (
        <>
            <Head title={auditLog.action.label} />
            <PageContainer>
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to Audit Log
                        </Link>
                    }
                    description="Read-only event details from the authoritative audit trail."
                    eyebrow={auditLog.action.value}
                    title={auditLog.action.label}
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <Panel className="p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-text">
                            Event context
                        </h2>
                        <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Occurred
                                </dt>
                                <dd className="mt-1 text-text tabular-nums">
                                    {formatDateTime(auditLog.occurredAt)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Record type
                                </dt>
                                <dd className="mt-1 text-text">
                                    {auditLog.subject.type}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Record reference
                                </dt>
                                <dd className="mt-1 font-medium text-text">
                                    {auditLog.subject.reference ??
                                        `Internal record #${auditLog.subject.internalId}`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Audit entry
                                </dt>
                                <dd className="mt-1 text-text tabular-nums">
                                    #{auditLog.id}
                                </dd>
                            </div>
                        </dl>
                    </Panel>

                    <Panel className="p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-text">
                            Actor
                        </h2>
                        {auditLog.actor ? (
                            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Account
                                    </dt>
                                    <dd className="mt-1 font-medium text-text">
                                        {auditLog.actor.name}
                                    </dd>
                                    <dd className="mt-1 text-sm text-text-secondary">
                                        {auditLog.actor.email}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Current account status
                                    </dt>
                                    <dd className="mt-1">
                                        <StatusBadge
                                            tone={
                                                auditLog.actor.isActive
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {auditLog.actor.isActive
                                                ? 'Active'
                                                : 'Disabled'}
                                        </StatusBadge>
                                    </dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Role at event time
                                    </dt>
                                    <dd className="mt-1 text-sm text-text-secondary">
                                        Not recorded by the existing audit
                                        schema. The account’s current role is
                                        not used as historical evidence.
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <p className="mt-4 text-sm leading-6 text-text-secondary">
                                This event was recorded by a controlled system
                                or bootstrap process without an authenticated
                                actor.
                            </p>
                        )}
                    </Panel>
                </div>

                <Panel className="p-5 sm:p-6">
                    <h2 className="text-lg font-semibold text-text">
                        Safe event data
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-text-secondary">
                        Only approved structural fields are shown. Raw audit
                        payloads and clinical narratives are never rendered.
                    </p>
                    {auditLog.changes.length === 0 &&
                    auditLog.metadata.length === 0 ? (
                        <p className="mt-5 rounded-control border border-border bg-surface-subtle p-4 text-sm text-text-secondary">
                            No additional review-safe fields were recorded for
                            this event.
                        </p>
                    ) : (
                        <dl className="mt-5 divide-y divide-border rounded-control border border-border">
                            {auditLog.changes.map((change) => (
                                <div
                                    className="grid gap-2 px-4 py-4 sm:grid-cols-[13rem_1fr]"
                                    key={change.field}
                                >
                                    <dt className="font-medium text-text">
                                        {change.label}
                                    </dt>
                                    <dd className="text-sm text-text-secondary">
                                        {change.before === null ? (
                                            formatValue(
                                                change.field,
                                                change.after,
                                            )
                                        ) : (
                                            <>
                                                {formatValue(
                                                    change.field,
                                                    change.before,
                                                )}{' '}
                                                <span
                                                    aria-hidden="true"
                                                    className="px-1 text-text-muted"
                                                >
                                                    →
                                                </span>{' '}
                                                {formatValue(
                                                    change.field,
                                                    change.after,
                                                )}
                                            </>
                                        )}
                                    </dd>
                                </div>
                            ))}
                            {auditLog.metadata.map((item) => (
                                <div
                                    className="grid gap-2 px-4 py-4 sm:grid-cols-[13rem_1fr]"
                                    key={item.field}
                                >
                                    <dt className="font-medium text-text">
                                        {item.label}
                                    </dt>
                                    <dd className="text-sm text-text-secondary">
                                        {formatValue(item.field, item.value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}

AuditLogShow.layout = [AuthenticatedLayout];
