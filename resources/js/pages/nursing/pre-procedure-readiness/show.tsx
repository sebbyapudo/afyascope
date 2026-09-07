import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { cn } from '@/lib/utils';
import {
    complete,
    index,
    update,
} from '@/routes/nursing/pre-procedure-readiness';
import type { PreProcedureReadinessWorkspace } from '@/types';

type PreProcedureReadinessShowProps = {
    readiness: PreProcedureReadinessWorkspace;
    status?: string | null;
};

const readinessChecks = [
    {
        description:
            'Required consent has been verified. This records verification only; it is not an electronic signature.',
        label: 'Consent verified',
        name: 'consent_verified',
        property: 'consentVerified',
    },
    {
        description:
            'Patient identity matches the current Patient and Visit context.',
        label: 'Patient identity verified',
        name: 'patient_identity_verified',
        property: 'patientIdentityVerified',
    },
    {
        description:
            "The preparation matches the Doctor's read-only selected procedure.",
        label: 'Procedure verified',
        name: 'procedure_verified',
        property: 'procedureVerified',
    },
    {
        description:
            'The existing allergy context has been reviewed for preparation.',
        label: 'Allergies reviewed',
        name: 'allergies_reviewed',
        property: 'allergiesReviewed',
    },
    {
        description:
            'The existing medication context has been reviewed for preparation.',
        label: 'Medications reviewed',
        name: 'medications_reviewed',
        property: 'medicationsReviewed',
    },
] as const;

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function PreProcedureReadinessShow({
    readiness,
    status,
}: PreProcedureReadinessShowProps) {
    const allChecksComplete = readinessChecks.every(
        (check) => readiness.checks[check.property],
    );

    return (
        <>
            <Head title={readiness.readinessNumber} />
            <PageContainer width="wide">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to preparation queue
                        </Link>
                    }
                    description="Nurse-owned consent verification and pre-procedure clinical readiness"
                    eyebrow={readiness.readinessNumber}
                    title={readiness.patient.name}
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
                    <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Read-only upstream context
                            </h2>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-text-secondary">
                                The Doctor owns the selected procedure and the
                                Accountant owns financial clearance. Nursing
                                preparation does not change either decision.
                            </p>
                        </div>
                        <StatusBadge
                            tone={
                                readiness.status.value === 'ready'
                                    ? 'success'
                                    : 'info'
                            }
                        >
                            {readiness.status.label}
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {readiness.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {readiness.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Identity context
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                Date of birth:{' '}
                                {readiness.patient.dateOfBirth ??
                                    'Not recorded'}
                                <span className="mt-1 block text-text-secondary">
                                    Sex:{' '}
                                    {readiness.patient.sex ?? 'Not recorded'}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {readiness.visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(readiness.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Selected procedure
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {readiness.procedure.name}
                                <span className="mt-1 block font-normal text-text-secondary tabular-nums">
                                    {readiness.procedure.decisionNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Doctor
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {readiness.doctor.name}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    Procedure authority remains with Doctor
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Nurse
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {readiness.nurse.name}
                                <span className="mt-1 block font-normal text-text-secondary">
                                    Started{' '}
                                    {formatDateTime(readiness.startedAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Existing allergies context
                            </dt>
                            <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                {readiness.clinicalContext.allergies ??
                                    'Not recorded'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Existing medications context
                            </dt>
                            <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                {readiness.clinicalContext.currentMedications ??
                                    'Not recorded'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                ASA classification
                            </dt>
                            <dd className="mt-2 text-sm text-text-secondary">
                                {readiness.clinicalContext.asaClassification ??
                                    'Not recorded'}
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 border-b border-border pb-5">
                        <h2 className="text-lg font-semibold text-text">
                            Nurse-owned readiness documentation
                        </h2>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-text-secondary">
                            {readiness.canManage
                                ? 'Save preparation progress, then complete readiness only after every mandatory verification is true.'
                                : readiness.status.value === 'ready'
                                  ? 'Completed readiness is immutable for the MVP.'
                                  : `Read-only record. This preparation is owned by ${readiness.nurse.name}.`}
                        </p>
                    </div>

                    {readiness.canManage ? (
                        <Form {...update.form(readiness.id)}>
                            {({ errors, processing }) => (
                                <div className="grid gap-6">
                                    {errors.readiness ? (
                                        <p
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.readiness}
                                        </p>
                                    ) : null}

                                    <fieldset className="grid gap-3">
                                        <legend className="text-sm font-semibold text-text">
                                            Mandatory readiness checks
                                        </legend>
                                        {readinessChecks.map((check) => (
                                            <label
                                                className="flex gap-3 rounded-control border border-border bg-surface-subtle p-4 transition-colors focus-within:border-brand-primary"
                                                key={check.name}
                                            >
                                                <input
                                                    name={check.name}
                                                    type="hidden"
                                                    value="0"
                                                />
                                                <input
                                                    className="mt-1 size-4 accent-brand-primary"
                                                    defaultChecked={
                                                        readiness.checks[
                                                            check.property
                                                        ]
                                                    }
                                                    name={check.name}
                                                    type="checkbox"
                                                    value="1"
                                                />
                                                <span>
                                                    <span className="block text-sm font-medium text-text">
                                                        {check.label}
                                                    </span>
                                                    <span className="mt-1 block text-xs leading-5 text-text-secondary">
                                                        {check.description}
                                                    </span>
                                                </span>
                                            </label>
                                        ))}
                                    </fieldset>

                                    <FormField
                                        error={errors.observations}
                                        hint="Optional preparation observations. Clinical narrative is never copied into audit metadata."
                                        id="readiness-observations"
                                        label="Preparation observations"
                                    >
                                        <textarea
                                            aria-describedby={
                                                errors.observations
                                                    ? 'readiness-observations-error'
                                                    : 'readiness-observations-hint'
                                            }
                                            aria-invalid={Boolean(
                                                errors.observations,
                                            )}
                                            className={cn(
                                                formControlStyles,
                                                'min-h-32 resize-y py-3',
                                            )}
                                            defaultValue={
                                                readiness.observations ?? ''
                                            }
                                            id="readiness-observations"
                                            maxLength={5000}
                                            name="observations"
                                            rows={5}
                                        />
                                    </FormField>

                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                            variant="secondary"
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save preparation progress'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    ) : (
                        <div className="grid gap-6">
                            <dl className="grid gap-3 md:grid-cols-2">
                                {readinessChecks.map((check) => (
                                    <div
                                        className="rounded-control border border-border bg-surface-subtle p-4"
                                        key={check.name}
                                    >
                                        <dt className="text-sm font-medium text-text">
                                            {check.label}
                                        </dt>
                                        <dd className="mt-2">
                                            <StatusBadge
                                                tone={
                                                    readiness.checks[
                                                        check.property
                                                    ]
                                                        ? 'success'
                                                        : 'warning'
                                                }
                                            >
                                                {readiness.checks[
                                                    check.property
                                                ]
                                                    ? 'Verified'
                                                    : 'Not verified'}
                                            </StatusBadge>
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <div>
                                <h3 className="text-sm font-semibold text-text">
                                    Preparation observations
                                </h3>
                                <p className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                    {readiness.observations ?? 'Not recorded'}
                                </p>
                            </div>
                        </div>
                    )}
                </Panel>

                {readiness.canComplete ? (
                    <Panel className="border-info-border bg-info-soft p-5 sm:p-8">
                        <h2 className="text-lg font-semibold text-text">
                            Complete readiness
                        </h2>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">
                            Completion is permanent for the MVP and hands the
                            Visit back to the responsible Doctor as ready for
                            procedure. It does not start or document the
                            procedure.
                        </p>
                        {!allChecksComplete ? (
                            <p className="mt-4 text-sm font-medium text-warning">
                                Save all five mandatory checks as verified
                                before completing readiness.
                            </p>
                        ) : null}
                        <Form {...complete.form(readiness.id)} className="mt-5">
                            {({ errors, processing }) => (
                                <div className="grid gap-3">
                                    {errors.readiness ? (
                                        <p
                                            className="text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.readiness}
                                        </p>
                                    ) : null}
                                    <div>
                                        <Button
                                            disabled={
                                                processing || !allChecksComplete
                                            }
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Completing…'
                                                : 'Complete readiness'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </Panel>
                ) : null}

                <Panel className="p-5 sm:p-8">
                    <h2 className="text-lg font-semibold text-text">
                        Workflow state
                    </h2>
                    <p className="mt-2 text-sm text-text-secondary">
                        {readiness.visit.nextStep}
                    </p>
                    {readiness.completedAt ? (
                        <p className="mt-2 text-xs text-text-secondary">
                            Completed {formatDateTime(readiness.completedAt)} by{' '}
                            {readiness.nurse.name}.
                        </p>
                    ) : null}
                </Panel>
            </PageContainer>
        </>
    );
}

PreProcedureReadinessShow.layout = [AuthenticatedLayout];
