import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index as patientIndex } from '@/routes/patients';
import { index, show } from '@/routes/visits';
import type { VisitPage } from '@/types';

type VisitIndexProps = {
    filters: { q: string };
    visits: VisitPage;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function VisitIndex({ filters, visits }: VisitIndexProps) {
    const { props } = usePage();
    const { data, pagination } = visits;

    return (
        <>
            <Head title="Visits" />
            <PageContainer width="wide">
                <PageHeader
                    actions={
                        props.auth.capabilities.createVisits ? (
                            <ActionLink href={patientIndex()}>
                                Find Patient to start Visit
                            </ActionLink>
                        ) : null
                    }
                    description="Administrative Visit records and their current operational handoff."
                    title="Visits"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ processing }) => (
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div className="min-w-0 flex-1">
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="visit-search"
                                    >
                                        Search Visits
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.q}
                                        id="visit-search"
                                        maxLength={100}
                                        name="q"
                                        placeholder="Visit reference, Patient reference, or Patient name"
                                        type="search"
                                    />
                                </div>
                                <div className="flex gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing ? 'Searching…' : 'Search'}
                                    </Button>
                                    {filters.q ? (
                                        <ActionLink
                                            href={index()}
                                            variant="secondary"
                                        >
                                            Clear
                                        </ActionLink>
                                    ) : null}
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            action={
                                filters.q ? (
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear search
                                    </ActionLink>
                                ) : null
                            }
                            description={
                                filters.q
                                    ? `No Visit records match “${filters.q}”.`
                                    : 'Created Visit records will appear here.'
                            }
                            title={
                                filters.q
                                    ? 'No matching Visits'
                                    : 'No Visits created'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-5xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4">Visit</th>
                                        <th className="px-5 py-4">Patient</th>
                                        <th className="px-5 py-4">Occurred</th>
                                        <th className="px-5 py-4">Status</th>
                                        <th className="px-5 py-4">
                                            Next handoff
                                        </th>
                                        <th className="px-5 py-4 text-right">
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
                                            <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                {visit.visitNumber}
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {visit.patient.name}
                                                </p>
                                                <p className="text-xs text-text-secondary tabular-nums">
                                                    {
                                                        visit.patient
                                                            .patientNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(
                                                    visit.occurredAt,
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="info">
                                                    {visit.status.label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {visit.nextStep}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={show(visit.id)}
                                                >
                                                    View
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
                                aria-label="Visit registry pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                page:
                                                    pagination.currentPage - 1,
                                                q: filters.q || undefined,
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
                                                q: filters.q || undefined,
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

VisitIndex.layout = [AuthenticatedLayout];
