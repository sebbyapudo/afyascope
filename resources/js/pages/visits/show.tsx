import { Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatMinorAmount } from '@/lib/money';
import { show as showAppointment } from '@/routes/appointments';
import {
    create as createCheckIn,
    show as showCheckIn,
} from '@/routes/check-ins';
import { show as showPatient } from '@/routes/patients';
import type { VisitSummary } from '@/types';

type ShowVisitProps = { status?: string | null; visit: VisitSummary };

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ShowVisit({ status, visit }: ShowVisitProps) {
    const { props } = usePage();
    const canCheckIn =
        props.auth.capabilities.createCheckIns && visit.canCheckIn;

    return (
        <>
            <Head title={visit.visitNumber} />
            <PageContainer>
                <PageHeader
                    actions={
                        canCheckIn ? (
                            <ActionLink href={createCheckIn(visit.id)}>
                                Check in Patient
                            </ActionLink>
                        ) : visit.checkIn &&
                          props.auth.capabilities.viewCheckIns ? (
                            <ActionLink
                                href={showCheckIn(visit.checkIn.id)}
                                variant="secondary"
                            >
                                View Check-in
                            </ActionLink>
                        ) : null
                    }
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={
                                visit.appointment
                                    ? showAppointment(visit.appointment.id)
                                    : showPatient(visit.patient.id)
                            }
                        >
                            {visit.appointment
                                ? 'Back to originating Appointment'
                                : 'Back to Patient profile'}
                        </Link>
                    }
                    description="Administrative Visit record"
                    eyebrow={visit.visitNumber}
                    title={visit.patient.name}
                />
                {status ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {status}
                    </p>
                ) : null}
                <Panel className="p-5 sm:p-8">
                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2">
                                <Link
                                    className={textLinkStyles}
                                    href={showPatient(visit.patient.id)}
                                >
                                    {visit.patient.name}
                                </Link>
                                <span className="mt-1 block text-sm text-text-secondary tabular-nums">
                                    {visit.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Occurred
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {formatDateTime(visit.occurredAt)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Current status
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge
                                    tone={
                                        visit.status.value === 'checked_in'
                                            ? 'success'
                                            : 'info'
                                    }
                                >
                                    {visit.status.label}
                                </StatusBadge>
                            </dd>
                        </div>
                        {visit.appointment ? (
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Originating Appointment
                                </dt>
                                <dd className="mt-2">
                                    <Link
                                        className={textLinkStyles}
                                        href={showAppointment(
                                            visit.appointment.id,
                                        )}
                                    >
                                        {visit.appointment.appointmentNumber}
                                    </Link>
                                </dd>
                            </div>
                        ) : null}
                    </dl>
                </Panel>
                {visit.consultationBill ? (
                    <Panel className="p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-text">
                            Consultation Bill
                        </h2>
                        <dl className="mt-4 grid gap-x-8 gap-y-5 sm:grid-cols-3">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Bill reference
                                </dt>
                                <dd className="mt-2 text-sm font-semibold text-brand-primary tabular-nums">
                                    {visit.consultationBill.billNumber}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Amount
                                </dt>
                                <dd className="mt-2 text-sm text-text tabular-nums">
                                    {formatMinorAmount(
                                        visit.consultationBill.totalAmountMinor,
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Bill status
                                </dt>
                                <dd className="mt-2">
                                    <StatusBadge tone="warning">
                                        {visit.consultationBill.status.label}
                                    </StatusBadge>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Financial clearance
                                </dt>
                                <dd className="mt-2">
                                    <StatusBadge
                                        tone={
                                            visit.consultationBill
                                                .isFinanciallyCleared
                                                ? 'success'
                                                : 'warning'
                                        }
                                    >
                                        {visit.consultationBill
                                            .isFinanciallyCleared
                                            ? 'Financially cleared'
                                            : 'Not financially cleared'}
                                    </StatusBadge>
                                </dd>
                            </div>
                        </dl>
                    </Panel>
                ) : null}
                {visit.checkIn ? (
                    <Panel className="p-5 sm:p-6">
                        <h2 className="text-lg font-semibold text-text">
                            Reception check-in
                        </h2>
                        <dl className="mt-4 grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Check-in reference
                                </dt>
                                <dd className="mt-2">
                                    <Link
                                        className={textLinkStyles}
                                        href={showCheckIn(visit.checkIn.id)}
                                    >
                                        {visit.checkIn.checkInNumber}
                                    </Link>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    Checked in
                                </dt>
                                <dd className="mt-2 text-sm text-text">
                                    {formatDateTime(visit.checkIn.checkedInAt)}
                                </dd>
                            </div>
                        </dl>
                    </Panel>
                ) : null}
                <Panel className="border-info-border bg-info-soft p-5 shadow-none sm:p-6">
                    <h2 className="font-semibold text-info">Next handoff</h2>
                    <p className="mt-1 text-sm text-info">{visit.nextStep}</p>
                </Panel>
            </PageContainer>
        </>
    );
}

ShowVisit.layout = [AuthenticatedLayout];
