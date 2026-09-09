import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { cn } from '@/lib/utils';
import { show as procedureShow } from '@/routes/clinical/procedures';
import { index as recoveryIndex } from '@/routes/nursing/recovery';
import { store as observationStore } from '@/routes/nursing/recovery/observations';
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
    return (
        <>
            <Head title={recovery.recoveryNumber} />
            <PageContainer width="wide">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={
                                recovery.canManage
                                    ? recoveryIndex()
                                    : procedureShow(recovery.procedure.id)
                            }
                        >
                            {recovery.canManage
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
                                {recovery.canManage
                                    ? 'You are the responsible Nurse for this in-progress recovery episode.'
                                    : `Read-only record owned by ${recovery.nurse.name}.`}
                            </p>
                        </div>
                        <StatusBadge tone="info">
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
