import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show as recoveryShow } from '@/routes/nursing/recovery';
import type { RecoveryEscalationQueueItem } from '@/types';

type RecoveryEscalationIndexProps = {
    escalations: RecoveryEscalationQueueItem[];
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function RecoveryEscalationIndex({
    escalations,
}: RecoveryEscalationIndexProps) {
    return (
        <>
            <Head title="Recovery review" />
            <PageContainer width="wide">
                <PageHeader
                    description="Open clinical concerns escalated by the responsible recovery Nurse for Doctor review."
                    title="Recovery review"
                />

                <Panel className="overflow-hidden">
                    <div className="border-b border-border px-5 py-4 sm:px-8">
                        <h2 className="font-semibold text-text">
                            Doctor review required
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Oldest open escalation first. Doctor resolution
                            returns care to the responsible Nurse.
                        </p>
                    </div>

                    {escalations.length === 0 ? (
                        <EmptyState
                            description="Nurse escalations will appear here until a Doctor records a clinical resolution."
                            title="No open recovery escalations"
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
                                            Recovery
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Concern
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Escalated
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
                                    {escalations.map((escalation) => (
                                        <tr
                                            className="align-top transition-colors hover:bg-canvas"
                                            key={escalation.id}
                                        >
                                            <td className="px-5 py-4">
                                                <p className="font-medium text-text">
                                                    {escalation.patient.name}
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary tabular-nums">
                                                    {
                                                        escalation.patient
                                                            .patientNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                <p className="font-semibold text-brand-primary tabular-nums">
                                                    {
                                                        escalation.recovery
                                                            .recoveryNumber
                                                    }
                                                </p>
                                                <p className="mt-1 text-xs text-text-secondary">
                                                    {escalation.procedure.name}{' '}
                                                    ·{' '}
                                                    {
                                                        escalation.visit
                                                            .visitNumber
                                                    }
                                                </p>
                                            </td>
                                            <td className="max-w-md px-5 py-4 text-text-secondary">
                                                {escalation.reason}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDateTime(
                                                    escalation.escalatedAt,
                                                )}
                                                <span className="mt-1 block text-xs">
                                                    by{' '}
                                                    {
                                                        escalation.escalatedBy
                                                            .name
                                                    }
                                                </span>
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge tone="warning">
                                                    Doctor review required
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ActionLink
                                                    href={recoveryShow(
                                                        escalation.recovery.id,
                                                    )}
                                                    size="small"
                                                >
                                                    Review recovery
                                                </ActionLink>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}

RecoveryEscalationIndex.layout = [AuthenticatedLayout];
