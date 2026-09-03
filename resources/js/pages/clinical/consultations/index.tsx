import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { create, index, show } from '@/routes/clinical/consultations';
import type { ClinicalQueue, InProgressConsultationQueue } from '@/types';

type ConsultationIndexProps = {
    readyVisits: ClinicalQueue;
    inProgressConsultations: InProgressConsultationQueue;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ConsultationIndex({
    readyVisits,
    inProgressConsultations,
}: ConsultationIndexProps) {
    return (
        <>
            <Head title="Doctor consultations" />
            <PageContainer width="wide">
                <PageHeader
                    description="Checked-in Visits ready for consultation and your active consultation work."
                    title="Doctor consultations"
                />

                <Panel className="overflow-hidden">
                    <header className="border-b border-border px-5 py-4">
                        <h2 className="font-semibold text-text">
                            Ready for consultation
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Oldest Reception check-in is shown first.
                        </p>
                    </header>

                    {readyVisits.data.length === 0 ? (
                        <EmptyState
                            description="Visits leave this queue when a Doctor begins consultation."
                            title="No checked-in Visits are waiting"
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
                                            Check-in
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
                                    {readyVisits.data.map((visit) => (
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
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(
                                                    visit.checkIn.checkedInAt,
                                                )}
                                                <span className="mt-1 block text-xs tabular-nums">
                                                    {
                                                        visit.checkIn
                                                            .checkInNumber
                                                    }
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="success">
                                                    {visit.status.label}
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
                                                    Open Visit
                                                </ActionLink>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {readyVisits.pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {readyVisits.pagination.from}–
                                {readyVisits.pagination.to} of{' '}
                                {readyVisits.pagination.total}
                            </p>
                            <nav
                                aria-label="Ready consultation queue pagination"
                                className="flex items-center gap-2"
                            >
                                {readyVisits.pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressConsultations
                                                        .pagination.currentPage,
                                                ready_page:
                                                    readyVisits.pagination
                                                        .currentPage - 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {readyVisits.pagination.currentPage <
                                readyVisits.pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressConsultations
                                                        .pagination.currentPage,
                                                ready_page:
                                                    readyVisits.pagination
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
                            My consultations in progress
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Only consultations assigned to you appear here.
                        </p>
                    </header>

                    {inProgressConsultations.data.length === 0 ? (
                        <EmptyState
                            description="A consultation appears here after you begin it from the ready queue."
                            title="No consultations in progress"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-3xl text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Consultation
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Started
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
                                    {inProgressConsultations.data.map(
                                        (consultation) => (
                                            <tr
                                                className="transition-colors hover:bg-canvas"
                                                key={consultation.id}
                                            >
                                                <td className="px-5 py-4">
                                                    <p className="font-medium text-text">
                                                        {
                                                            consultation.visit
                                                                .patient.name
                                                        }
                                                    </p>
                                                    <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                        {
                                                            consultation.visit
                                                                .patient
                                                                .patientNumber
                                                        }
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <p className="font-semibold text-brand-primary tabular-nums">
                                                        {
                                                            consultation.consultationNumber
                                                        }
                                                    </p>
                                                    <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                        {
                                                            consultation.visit
                                                                .visitNumber
                                                        }
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4 text-text-secondary">
                                                    {formatDateTime(
                                                        consultation.startedAt,
                                                    )}
                                                </td>
                                                <td className="px-5 py-4 text-right">
                                                    <ActionLink
                                                        href={show(
                                                            consultation.id,
                                                        )}
                                                        size="small"
                                                        variant="secondary"
                                                    >
                                                        Open workspace
                                                    </ActionLink>
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {inProgressConsultations.pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing{' '}
                                {inProgressConsultations.pagination.from}–
                                {inProgressConsultations.pagination.to} of{' '}
                                {inProgressConsultations.pagination.total}
                            </p>
                            <nav
                                aria-label="My consultations pagination"
                                className="flex items-center gap-2"
                            >
                                {inProgressConsultations.pagination
                                    .currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressConsultations
                                                        .pagination
                                                        .currentPage - 1,
                                                ready_page:
                                                    readyVisits.pagination
                                                        .currentPage,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {inProgressConsultations.pagination
                                    .currentPage <
                                inProgressConsultations.pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                in_progress_page:
                                                    inProgressConsultations
                                                        .pagination
                                                        .currentPage + 1,
                                                ready_page:
                                                    readyVisits.pagination
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

ConsultationIndex.layout = [AuthenticatedLayout];
