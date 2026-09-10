import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { cn } from '@/lib/utils';
import { show as procedureShow } from '@/routes/clinical/procedures';
import { update as escalationUpdate } from '@/routes/clinical/recovery-escalations';
import { index as recoveryIndex } from '@/routes/nursing/recovery';
import { store as dischargeStore } from '@/routes/nursing/recovery/discharge';
import { store as observationStore } from '@/routes/nursing/recovery/observations';
import { store as readinessStore } from '@/routes/nursing/recovery/readiness';
import type { RecoveryWorkspace } from '@/types';

type RecoveryShowProps = {
    recovery: RecoveryWorkspace;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function RecoveryShow({ recovery, status }: RecoveryShowProps) {
    const [criteriaMet, setCriteriaMet] = useState(false);
    const [requiresEscalation, setRequiresEscalation] = useState(false);
    const openEscalation = recovery.escalations.find(
        (escalation) => escalation.status.value === 'open',
    );

    return (
        <>
            <Head title={recovery.recoveryNumber} />
            <PageContainer width="wide">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={
                                recovery.isResponsibleNurse
                                    ? recoveryIndex()
                                    : procedureShow(recovery.procedure.id)
                            }
                        >
                            {recovery.isResponsibleNurse
                                ? 'Back to recovery worklist'
                                : 'Back to procedure'}
                        </Link>
                    }
                    description="Nurse-owned post-procedure monitoring and serial recovery documentation"
                    eyebrow="Nursing recovery"
                    title={recovery.recoveryNumber}
                />

                {status ? (
                    <div
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {status}
                    </div>
                ) : null}

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Recovery context
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                {recovery.discharge
                                    ? `Finalized by ${recovery.discharge.dischargedBy.name} on ${formatDateTime(recovery.discharge.dischargedAt)}.`
                                    : recovery.canManage
                                      ? 'You are the responsible Nurse for this in-progress recovery episode.'
                                      : recovery.canDischarge
                                        ? 'You are the responsible Nurse and this recovery is ready for discharge.'
                                        : `Read-only record owned by ${recovery.nurse.name}.`}
                            </p>
                        </div>
                        <StatusBadge
                            tone={
                                recovery.status.value ===
                                    'ready_for_discharge' ||
                                recovery.status.value === 'completed'
                                    ? 'success'
                                    : openEscalation
                                      ? 'warning'
                                      : 'info'
                            }
                        >
                            {recovery.status.label}
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {recovery.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {recovery.visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(recovery.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Procedure
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.procedure.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {recovery.procedure.procedureNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Doctor
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.doctor.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    Procedure authority
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Nurse
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {recovery.nurse.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    Started {formatDateTime(recovery.startedAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Workflow state
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-info">
                                {recovery.visit.nextStep}
                            </dd>
                        </div>
                    </dl>
                </Panel>

                {openEscalation ? (
                    <Panel className="border-warning-border bg-warning-soft p-5 sm:p-8">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="max-w-3xl">
                                <h2 className="text-lg font-semibold text-text">
                                    Doctor review required
                                </h2>
                                <p className="mt-2 text-sm leading-6 text-text-secondary">
                                    Escalated by{' '}
                                    {openEscalation.escalatedBy.name} on{' '}
                                    {formatDateTime(openEscalation.escalatedAt)}
                                    . The patient cannot become discharge-ready
                                    until this escalation is resolved.
                                </p>
                                <p className="mt-4 text-sm leading-6 whitespace-pre-wrap text-text">
                                    {openEscalation.reason}
                                </p>
                            </div>
                            <StatusBadge tone="warning">
                                {openEscalation.status.label}
                            </StatusBadge>
                        </div>

                        {recovery.canResolveEscalation ? (
                            <Form
                                {...escalationUpdate.form(openEscalation.id)}
                                className="mt-6 grid gap-5 border-t border-warning-border pt-6"
                                resetOnSuccess
                            >
                                {({ errors, processing }) => (
                                    <>
                                        {errors.escalation ||
                                        errors.recovery ||
                                        errors.actor ? (
                                            <p
                                                className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                                role="alert"
                                            >
                                                {errors.escalation ??
                                                    errors.recovery ??
                                                    errors.actor}
                                            </p>
                                        ) : null}
                                        <fieldset className="grid gap-3">
                                            <legend className="text-sm font-semibold text-text">
                                                Clinical resolution
                                            </legend>
                                            <label className="flex items-start gap-3 rounded-control border border-border bg-surface p-4 focus-within:border-brand-primary">
                                                <input
                                                    className="mt-0.5 size-4 accent-brand-primary"
                                                    name="resolution"
                                                    required
                                                    type="radio"
                                                    value="continue_monitoring"
                                                />
                                                <span>
                                                    <span className="block text-sm font-medium text-text">
                                                        Continue Nursing
                                                        monitoring
                                                    </span>
                                                    <span className="mt-1 block text-xs text-text-secondary">
                                                        The responsible Nurse
                                                        continues observations
                                                        and may reassess
                                                        readiness.
                                                    </span>
                                                </span>
                                            </label>
                                            <label className="flex items-start gap-3 rounded-control border border-border bg-surface p-4 focus-within:border-brand-primary">
                                                <input
                                                    className="mt-0.5 size-4 accent-brand-primary"
                                                    name="resolution"
                                                    required
                                                    type="radio"
                                                    value="clinically_cleared"
                                                />
                                                <span>
                                                    <span className="block text-sm font-medium text-text">
                                                        Clinically cleared
                                                    </span>
                                                    <span className="mt-1 block text-xs text-text-secondary">
                                                        Return readiness
                                                        authority to the
                                                        responsible Nurse. This
                                                        does not discharge the
                                                        patient.
                                                    </span>
                                                </span>
                                            </label>
                                        </fieldset>
                                        <FormField
                                            error={errors.resolution_note}
                                            hint="Optional concise clinical resolution. Maximum 1,000 characters."
                                            id="resolution-note"
                                            label="Resolution note"
                                        >
                                            <textarea
                                                aria-invalid={Boolean(
                                                    errors.resolution_note,
                                                )}
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-24 resize-y py-3',
                                                )}
                                                id="resolution-note"
                                                maxLength={1000}
                                                name="resolution_note"
                                                rows={3}
                                            />
                                        </FormField>
                                        <div>
                                            <Button
                                                disabled={processing}
                                                type="submit"
                                            >
                                                {processing
                                                    ? 'Resolving…'
                                                    : 'Resolve escalation'}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : null}
                    </Panel>
                ) : null}

                <Panel className="p-5 sm:p-8">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Recovery readiness
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                The responsible Nurse records the current
                                structured readiness assessment. Readiness is
                                not discharge.
                            </p>
                        </div>
                        {recovery.readinessAssessment ? (
                            <StatusBadge
                                tone={
                                    recovery.readinessAssessment.criteriaMet &&
                                    !recovery.readinessAssessment
                                        .clinicalConcernRequiresEscalation
                                        ? 'success'
                                        : recovery.readinessAssessment
                                                .clinicalConcernRequiresEscalation
                                          ? 'warning'
                                          : 'neutral'
                                }
                            >
                                {recovery.readinessAssessment.criteriaMet
                                    ? 'Criteria met'
                                    : 'Criteria not met'}
                            </StatusBadge>
                        ) : null}
                    </div>

                    {recovery.readinessAssessment ? (
                        <div className="mt-6 rounded-control border border-border bg-surface-subtle p-4">
                            <dl className="grid gap-4 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-text-secondary">
                                        Recovery criteria
                                    </dt>
                                    <dd className="mt-1 font-medium text-text">
                                        {recovery.readinessAssessment
                                            .criteriaMet
                                            ? 'Met'
                                            : 'Not yet met'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-text-secondary">
                                        Clinical concern
                                    </dt>
                                    <dd className="mt-1 font-medium text-text">
                                        {recovery.readinessAssessment
                                            .clinicalConcernRequiresEscalation
                                            ? 'Escalation required'
                                            : 'No escalation required'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-text-secondary">
                                        Assessed
                                    </dt>
                                    <dd className="mt-1 font-medium text-text">
                                        {formatDateTime(
                                            recovery.readinessAssessment
                                                .assessedAt,
                                        )}{' '}
                                        by{' '}
                                        {
                                            recovery.readinessAssessment
                                                .assessedBy.name
                                        }
                                    </dd>
                                </div>
                            </dl>
                            {recovery.readinessAssessment.assessmentNote ? (
                                <p className="mt-4 border-t border-border pt-4 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                    {
                                        recovery.readinessAssessment
                                            .assessmentNote
                                    }
                                </p>
                            ) : null}
                        </div>
                    ) : (
                        <p className="mt-6 text-sm text-text-secondary">
                            No recovery readiness assessment has been recorded.
                        </p>
                    )}

                    {recovery.canAssessReadiness ? (
                        <Form
                            {...readinessStore.form(recovery.id)}
                            className="mt-6 grid gap-6 border-t border-border pt-6"
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <>
                                    {errors.recovery || errors.actor ? (
                                        <p
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.recovery ?? errors.actor}
                                        </p>
                                    ) : null}
                                    <fieldset className="grid gap-3 sm:grid-cols-2">
                                        <legend className="mb-2 text-sm font-semibold text-text sm:col-span-2">
                                            Structured assessment
                                        </legend>
                                        <label className="flex items-center gap-3 rounded-control border border-border bg-surface-subtle p-4 focus-within:border-brand-primary">
                                            <input
                                                name="criteria_met"
                                                type="hidden"
                                                value="0"
                                            />
                                            <input
                                                checked={criteriaMet}
                                                className="size-4 accent-brand-primary"
                                                disabled={requiresEscalation}
                                                name="criteria_met"
                                                onChange={(event) =>
                                                    setCriteriaMet(
                                                        event.target.checked,
                                                    )
                                                }
                                                type="checkbox"
                                                value="1"
                                            />
                                            <span className="text-sm font-medium text-text">
                                                Required recovery criteria are
                                                met
                                            </span>
                                        </label>
                                        <label className="flex items-center gap-3 rounded-control border border-border bg-surface-subtle p-4 focus-within:border-brand-primary">
                                            <input
                                                name="clinical_concern_requires_escalation"
                                                type="hidden"
                                                value="0"
                                            />
                                            <input
                                                checked={requiresEscalation}
                                                className="size-4 accent-brand-primary"
                                                disabled={criteriaMet}
                                                name="clinical_concern_requires_escalation"
                                                onChange={(event) =>
                                                    setRequiresEscalation(
                                                        event.target.checked,
                                                    )
                                                }
                                                type="checkbox"
                                                value="1"
                                            />
                                            <span className="text-sm font-medium text-text">
                                                Clinical concern requires Doctor
                                                review
                                            </span>
                                        </label>
                                    </fieldset>
                                    {errors.criteria_met ||
                                    errors.clinical_concern_requires_escalation ? (
                                        <p
                                            className="text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.criteria_met ??
                                                errors.clinical_concern_requires_escalation}
                                        </p>
                                    ) : null}
                                    <FormField
                                        error={errors.assessment_note}
                                        hint="Optional concise readiness note. Maximum 1,000 characters."
                                        id="assessment-note"
                                        label="Assessment note"
                                    >
                                        <textarea
                                            aria-invalid={Boolean(
                                                errors.assessment_note,
                                            )}
                                            className={cn(
                                                formControlStyles,
                                                'min-h-24 resize-y py-3',
                                            )}
                                            id="assessment-note"
                                            maxLength={1000}
                                            name="assessment_note"
                                            rows={3}
                                        />
                                    </FormField>
                                    {requiresEscalation ? (
                                        <FormField
                                            error={errors.escalation_reason}
                                            hint="Required for Doctor review. Maximum 1,000 characters."
                                            id="escalation-reason"
                                            label="Clinical concern"
                                            required
                                        >
                                            <textarea
                                                aria-invalid={Boolean(
                                                    errors.escalation_reason,
                                                )}
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-24 resize-y py-3',
                                                )}
                                                id="escalation-reason"
                                                maxLength={1000}
                                                name="escalation_reason"
                                                required
                                                rows={3}
                                            />
                                        </FormField>
                                    ) : null}
                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Recording…'
                                                : requiresEscalation
                                                  ? 'Assess and escalate'
                                                  : 'Record readiness assessment'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ) : null}
                </Panel>

                {recovery.discharge ? (
                    <Panel className="p-5 sm:p-8 print:border-0 print:shadow-none">
                        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-5">
                            <div>
                                <h2 className="text-lg font-semibold text-text">
                                    Finalized discharge summary
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    {recovery.discharge.dischargeNumber} ·{' '}
                                    {formatDateTime(
                                        recovery.discharge.dischargedAt,
                                    )}{' '}
                                    by {recovery.discharge.dischargedBy.name}
                                </p>
                            </div>
                            <StatusBadge tone="success">Discharged</StatusBadge>
                        </div>

                        <dl className="mt-6 grid gap-5 text-sm md:grid-cols-3">
                            <div>
                                <dt className="text-text-secondary">
                                    Condition at discharge
                                </dt>
                                <dd className="mt-1 font-medium text-text">
                                    {recovery.discharge.conditionSummary}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-text-secondary">
                                    Accompaniment
                                </dt>
                                <dd className="mt-1 font-medium text-text">
                                    {
                                        recovery.discharge.accompanimentStatus
                                            .label
                                    }
                                </dd>
                            </div>
                            <div>
                                <dt className="text-text-secondary">
                                    Disposition
                                </dt>
                                <dd className="mt-1 font-medium text-text">
                                    {recovery.discharge.disposition.label}
                                </dd>
                            </div>
                        </dl>

                        {recovery.discharge.nursingNote ? (
                            <div className="mt-6 rounded-control border border-border bg-surface-subtle p-4">
                                <h3 className="text-sm font-semibold text-text">
                                    Nursing discharge note
                                </h3>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                    {recovery.discharge.nursingNote}
                                </p>
                            </div>
                        ) : null}

                        <div className="mt-6 border-t border-border pt-6">
                            <h3 className="font-semibold text-text">
                                Instructions communicated
                            </h3>
                            <dl className="mt-4 grid gap-5 md:grid-cols-2">
                                {[
                                    [
                                        'General post-procedure care',
                                        recovery.discharge
                                            .generalCareInstructions,
                                    ],
                                    [
                                        'Activity and driving restrictions',
                                        recovery.discharge
                                            .activityDrivingInstructions,
                                    ],
                                    [
                                        'Diet and fluids',
                                        recovery.discharge
                                            .dietFluidsInstructions,
                                    ],
                                    [
                                        'Medication-related guidance',
                                        recovery.discharge
                                            .medicationInstructions ??
                                            'Not applicable or none documented.',
                                    ],
                                    [
                                        'Warning signs and urgent care',
                                        recovery.discharge
                                            .warningSignsInstructions,
                                    ],
                                    [
                                        'Follow-up',
                                        recovery.discharge
                                            .followUpInstructions ??
                                            'Not applicable or none documented.',
                                    ],
                                ].map(([label, value]) => (
                                    <div
                                        className="rounded-control border border-border bg-surface-subtle p-4"
                                        key={label}
                                    >
                                        <dt className="text-sm font-semibold text-text">
                                            {label}
                                        </dt>
                                        <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </Panel>
                ) : recovery.canDischarge ? (
                    <Panel className="border-success-border p-5 sm:p-8">
                        <div className="border-b border-border pb-5">
                            <h2 className="text-lg font-semibold text-text">
                                Document and confirm discharge
                            </h2>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-text-secondary">
                                Review the ready state, record what was
                                communicated, then explicitly confirm the final
                                Nursing discharge. This action cannot be edited
                                or undone.
                            </p>
                        </div>

                        <Form
                            {...dischargeStore.form(recovery.id)}
                            className="mt-6 grid gap-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {errors.recovery || errors.actor ? (
                                        <p
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.recovery ?? errors.actor}
                                        </p>
                                    ) : null}

                                    <div className="grid gap-5 md:grid-cols-2">
                                        <FormField
                                            error={errors.condition_summary}
                                            hint="Concise clinical status at the point of discharge."
                                            id="discharge-condition-summary"
                                            label="Condition at discharge"
                                            required
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.condition_summary,
                                                )}
                                                className={formControlStyles}
                                                id="discharge-condition-summary"
                                                maxLength={255}
                                                name="condition_summary"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.accompaniment_status}
                                            id="discharge-accompaniment"
                                            label="Accompaniment status"
                                            required
                                        >
                                            <select
                                                aria-invalid={Boolean(
                                                    errors.accompaniment_status,
                                                )}
                                                className={formControlStyles}
                                                defaultValue=""
                                                id="discharge-accompaniment"
                                                name="accompaniment_status"
                                                required
                                            >
                                                <option disabled value="">
                                                    Select accompaniment
                                                </option>
                                                <option value="accompanied">
                                                    Accompanied
                                                </option>
                                                <option value="not_accompanied">
                                                    Not accompanied
                                                </option>
                                                <option value="not_applicable">
                                                    Not applicable
                                                </option>
                                            </select>
                                        </FormField>
                                        <FormField
                                            error={errors.disposition}
                                            id="discharge-disposition"
                                            label="Discharge disposition"
                                            required
                                        >
                                            <select
                                                aria-invalid={Boolean(
                                                    errors.disposition,
                                                )}
                                                className={formControlStyles}
                                                defaultValue=""
                                                id="discharge-disposition"
                                                name="disposition"
                                                required
                                            >
                                                <option disabled value="">
                                                    Select disposition
                                                </option>
                                                <option value="home">
                                                    Home
                                                </option>
                                                <option value="other_facility">
                                                    Other care facility
                                                </option>
                                                <option value="other">
                                                    Other destination
                                                </option>
                                            </select>
                                        </FormField>
                                        <FormField
                                            error={errors.nursing_note}
                                            hint="Optional concise Nursing discharge note."
                                            id="discharge-nursing-note"
                                            label="Nursing discharge note"
                                        >
                                            <textarea
                                                aria-invalid={Boolean(
                                                    errors.nursing_note,
                                                )}
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-24 resize-y py-3',
                                                )}
                                                id="discharge-nursing-note"
                                                maxLength={2000}
                                                name="nursing_note"
                                                rows={3}
                                            />
                                        </FormField>
                                    </div>

                                    <fieldset className="grid gap-5 border-t border-border pt-6 md:grid-cols-2">
                                        <legend className="mb-1 text-sm font-semibold text-text md:col-span-2">
                                            Instructions communicated to the
                                            patient
                                        </legend>
                                        {[
                                            [
                                                'general-care-instructions',
                                                'general_care_instructions',
                                                'General post-procedure care',
                                                true,
                                            ],
                                            [
                                                'activity-driving-instructions',
                                                'activity_driving_instructions',
                                                'Activity and driving restrictions',
                                                true,
                                            ],
                                            [
                                                'diet-fluids-instructions',
                                                'diet_fluids_instructions',
                                                'Diet and fluids guidance',
                                                true,
                                            ],
                                            [
                                                'medication-instructions',
                                                'medication_instructions',
                                                'Medication-related instructions',
                                                false,
                                            ],
                                            [
                                                'warning-signs-instructions',
                                                'warning_signs_instructions',
                                                'Warning signs and when to seek care',
                                                true,
                                            ],
                                            [
                                                'follow-up-instructions',
                                                'follow_up_instructions',
                                                'Follow-up instructions',
                                                false,
                                            ],
                                        ].map(([id, name, label, required]) => (
                                            <FormField
                                                error={errors[String(name)]}
                                                hint={
                                                    required
                                                        ? 'Document the instructions actually communicated.'
                                                        : 'Optional where applicable.'
                                                }
                                                id={String(id)}
                                                key={String(name)}
                                                label={String(label)}
                                                required={Boolean(required)}
                                            >
                                                <textarea
                                                    aria-invalid={Boolean(
                                                        errors[String(name)],
                                                    )}
                                                    className={cn(
                                                        formControlStyles,
                                                        'min-h-28 resize-y py-3',
                                                    )}
                                                    id={String(id)}
                                                    maxLength={5000}
                                                    name={String(name)}
                                                    required={Boolean(required)}
                                                    rows={4}
                                                />
                                            </FormField>
                                        ))}
                                    </fieldset>

                                    <label className="flex items-start gap-3 rounded-control border border-warning-border bg-warning-soft p-4 focus-within:border-brand-primary">
                                        <input
                                            className="mt-0.5 size-4 accent-brand-primary"
                                            name="confirm_discharge"
                                            required
                                            type="checkbox"
                                            value="1"
                                        />
                                        <span>
                                            <span className="block text-sm font-semibold text-text">
                                                I confirm the patient is ready
                                                and I am finalizing discharge
                                            </span>
                                            <span className="mt-1 block text-xs leading-5 text-text-secondary">
                                                The finalized record is
                                                read-only. Formal amendments are
                                                deferred rather than silently
                                                overwriting clinical history.
                                            </span>
                                            {errors.confirm_discharge ? (
                                                <span
                                                    className="mt-2 block text-sm text-danger"
                                                    role="alert"
                                                >
                                                    {errors.confirm_discharge}
                                                </span>
                                            ) : null}
                                        </span>
                                    </label>

                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Finalizing discharge…'
                                                : 'Confirm and discharge'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </Panel>
                ) : null}

                {recovery.escalations.length > 0 ? (
                    <Panel className="overflow-hidden">
                        <div className="border-b border-border px-5 py-5 sm:px-8">
                            <h2 className="text-lg font-semibold text-text">
                                Clinical escalation history
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Durable Nurse escalations and Doctor
                                resolutions, newest first.
                            </p>
                        </div>
                        <ol className="divide-y divide-border">
                            {recovery.escalations.map((escalation) => (
                                <li className="p-5 sm:p-8" key={escalation.id}>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-semibold text-text">
                                                Escalated{' '}
                                                {formatDateTime(
                                                    escalation.escalatedAt,
                                                )}{' '}
                                                by {escalation.escalatedBy.name}
                                            </p>
                                            <p className="mt-3 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                                {escalation.reason}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            tone={
                                                escalation.status.value ===
                                                'open'
                                                    ? 'warning'
                                                    : 'success'
                                            }
                                        >
                                            {escalation.status.label}
                                        </StatusBadge>
                                    </div>
                                    {escalation.resolution &&
                                    escalation.resolvedAt &&
                                    escalation.resolvedBy ? (
                                        <div className="mt-4 rounded-control border border-border bg-surface-subtle p-4 text-sm">
                                            <p className="font-medium text-text">
                                                {escalation.resolution.label}
                                            </p>
                                            <p className="mt-1 text-text-secondary">
                                                Resolved{' '}
                                                {formatDateTime(
                                                    escalation.resolvedAt,
                                                )}{' '}
                                                by {escalation.resolvedBy.name}
                                            </p>
                                            {escalation.resolutionNote ? (
                                                <p className="mt-3 leading-6 whitespace-pre-wrap text-text-secondary">
                                                    {escalation.resolutionNote}
                                                </p>
                                            ) : null}
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ol>
                    </Panel>
                ) : null}

                {recovery.canManage ? (
                    <Panel className="p-5 sm:p-8">
                        <div className="mb-6 border-b border-border pb-5">
                            <h2 className="text-lg font-semibold text-text">
                                Record recovery observation
                            </h2>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-text-secondary">
                                Add a new time-stamped observation. Existing
                                observations are append-only and recovery
                                remains in progress.
                            </p>
                        </div>
                        <Form
                            {...observationStore.form(recovery.id)}
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <div className="grid gap-6">
                                    {errors.recovery || errors.actor ? (
                                        <p
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.recovery ?? errors.actor}
                                        </p>
                                    ) : null}
                                    <FormField
                                        error={errors.general_recovery_status}
                                        id="general-recovery-status"
                                        label="General recovery status"
                                        required
                                    >
                                        <input
                                            aria-invalid={Boolean(
                                                errors.general_recovery_status,
                                            )}
                                            className={formControlStyles}
                                            id="general-recovery-status"
                                            maxLength={255}
                                            name="general_recovery_status"
                                            placeholder="For example: awake and recovering comfortably"
                                            required
                                        />
                                    </FormField>
                                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                        <FormField
                                            error={errors.pain_score}
                                            hint="Optional, 0–10."
                                            id="pain-score"
                                            label="Pain score"
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.pain_score,
                                                )}
                                                className={formControlStyles}
                                                id="pain-score"
                                                max={10}
                                                min={0}
                                                name="pain_score"
                                                type="number"
                                            />
                                        </FormField>
                                        <FormField
                                            error={
                                                errors.systolic_blood_pressure
                                            }
                                            hint="Record with diastolic pressure."
                                            id="systolic-blood-pressure"
                                            label="Systolic BP"
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.systolic_blood_pressure,
                                                )}
                                                className={formControlStyles}
                                                id="systolic-blood-pressure"
                                                max={300}
                                                min={30}
                                                name="systolic_blood_pressure"
                                                type="number"
                                            />
                                        </FormField>
                                        <FormField
                                            error={
                                                errors.diastolic_blood_pressure
                                            }
                                            hint="Record with systolic pressure."
                                            id="diastolic-blood-pressure"
                                            label="Diastolic BP"
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.diastolic_blood_pressure,
                                                )}
                                                className={formControlStyles}
                                                id="diastolic-blood-pressure"
                                                max={200}
                                                min={20}
                                                name="diastolic_blood_pressure"
                                                type="number"
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.pulse_rate}
                                            id="pulse-rate"
                                            label="Pulse rate"
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.pulse_rate,
                                                )}
                                                className={formControlStyles}
                                                id="pulse-rate"
                                                max={300}
                                                min={20}
                                                name="pulse_rate"
                                                type="number"
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.respiratory_rate}
                                            id="respiratory-rate"
                                            label="Respiratory rate"
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.respiratory_rate,
                                                )}
                                                className={formControlStyles}
                                                id="respiratory-rate"
                                                max={100}
                                                min={4}
                                                name="respiratory_rate"
                                                type="number"
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.oxygen_saturation}
                                            hint="Percentage, 0–100."
                                            id="oxygen-saturation"
                                            label="Oxygen saturation"
                                        >
                                            <input
                                                aria-invalid={Boolean(
                                                    errors.oxygen_saturation,
                                                )}
                                                className={formControlStyles}
                                                id="oxygen-saturation"
                                                max={100}
                                                min={0}
                                                name="oxygen_saturation"
                                                type="number"
                                            />
                                        </FormField>
                                    </div>
                                    <fieldset className="grid gap-3 sm:grid-cols-3">
                                        <legend className="mb-2 text-sm font-semibold text-text">
                                            Observed/supportive conditions
                                        </legend>
                                        {[
                                            ['nausea', 'Nausea present'],
                                            ['vomiting', 'Vomiting present'],
                                            [
                                                'supplemental_oxygen',
                                                'Supplemental oxygen in use',
                                            ],
                                        ].map(([name, label]) => (
                                            <label
                                                className="flex items-center gap-3 rounded-control border border-border bg-surface-subtle p-4 focus-within:border-brand-primary"
                                                key={name}
                                            >
                                                <input
                                                    name={name}
                                                    type="hidden"
                                                    value="0"
                                                />
                                                <input
                                                    className="size-4 accent-brand-primary"
                                                    name={name}
                                                    type="checkbox"
                                                    value="1"
                                                />
                                                <span className="text-sm font-medium text-text">
                                                    {label}
                                                </span>
                                            </label>
                                        ))}
                                    </fieldset>
                                    <FormField
                                        error={errors.nursing_note}
                                        hint="Optional recovery-specific Nursing note. Maximum 5,000 characters."
                                        id="nursing-note"
                                        label="Nursing note"
                                    >
                                        <textarea
                                            aria-invalid={Boolean(
                                                errors.nursing_note,
                                            )}
                                            className={cn(
                                                formControlStyles,
                                                'min-h-28 resize-y py-3',
                                            )}
                                            id="nursing-note"
                                            maxLength={5000}
                                            name="nursing_note"
                                            rows={4}
                                        />
                                    </FormField>
                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Recording…'
                                                : 'Record observation'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </Panel>
                ) : null}

                <Panel className="overflow-hidden">
                    <div className="border-b border-border px-5 py-5 sm:px-8">
                        <h2 className="text-lg font-semibold text-text">
                            Serial recovery observations
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Newest observation first. Recorded entries cannot be
                            edited or deleted.
                        </p>
                    </div>
                    {recovery.observations.length === 0 ? (
                        <div className="px-5 py-8 text-sm text-text-secondary sm:px-8">
                            No recovery observations have been recorded.
                        </div>
                    ) : (
                        <ol className="divide-y divide-border">
                            {recovery.observations.map((observation) => (
                                <li className="p-5 sm:p-8" key={observation.id}>
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 className="font-semibold text-text">
                                                {
                                                    observation.generalRecoveryStatus
                                                }
                                            </h3>
                                            <p className="mt-1 text-xs text-text-secondary">
                                                Recorded{' '}
                                                {formatDateTime(
                                                    observation.recordedAt,
                                                )}{' '}
                                                by {observation.recordedBy.name}
                                            </p>
                                        </div>
                                        <StatusBadge tone="info">
                                            Observation
                                        </StatusBadge>
                                    </div>
                                    <dl className="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <dt className="text-text-secondary">
                                                Pain score
                                            </dt>
                                            <dd className="mt-1 font-medium text-text">
                                                {observation.painScore ??
                                                    'Not recorded'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-text-secondary">
                                                Blood pressure
                                            </dt>
                                            <dd className="mt-1 font-medium text-text">
                                                {observation.systolicBloodPressure !==
                                                    null &&
                                                observation.diastolicBloodPressure !==
                                                    null
                                                    ? `${observation.systolicBloodPressure}/${observation.diastolicBloodPressure}`
                                                    : 'Not recorded'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-text-secondary">
                                                Pulse / respiratory
                                            </dt>
                                            <dd className="mt-1 font-medium text-text">
                                                {observation.pulseRate ?? '—'} /{' '}
                                                {observation.respiratoryRate ??
                                                    '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-text-secondary">
                                                Oxygen saturation
                                            </dt>
                                            <dd className="mt-1 font-medium text-text">
                                                {observation.oxygenSaturation !==
                                                null
                                                    ? `${observation.oxygenSaturation}%`
                                                    : 'Not recorded'}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p className="mt-4 text-sm text-text-secondary">
                                        Nausea:{' '}
                                        {observation.nausea ? 'Yes' : 'No'} ·
                                        Vomiting:{' '}
                                        {observation.vomiting ? 'Yes' : 'No'} ·
                                        Supplemental oxygen:{' '}
                                        {observation.supplementalOxygen
                                            ? 'Yes'
                                            : 'No'}
                                    </p>
                                    {observation.nursingNote ? (
                                        <p className="mt-4 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                            {observation.nursingNote}
                                        </p>
                                    ) : null}
                                </li>
                            ))}
                        </ol>
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}

RecoveryShow.layout = [AuthenticatedLayout];
