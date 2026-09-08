import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { cn } from '@/lib/utils';
import { complete, index, update } from '@/routes/clinical/procedures';
import { show as recoveryShow } from '@/routes/nursing/recovery';
import type { ProcedureRecordWorkspace } from '@/types';

type ProcedureShowProps = {
    procedure: ProcedureRecordWorkspace;
    status?: string | null;
};

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

function valueOrNotRecorded(value: string | null): string {
    return value ?? 'Not recorded';
}

export default function ProcedureShow({
    procedure,
    status,
}: ProcedureShowProps) {
    const isCompleted = procedure.status.value === 'completed';

    return (
        <>
            <Head title={procedure.procedureNumber} />
            <PageContainer width="wide">
                <PageHeader
                    backLink={
                        procedure.canManage ? (
                            <Link className={textLinkStyles} href={index()}>
                                Back to procedure queue
                            </Link>
                        ) : undefined
                    }
                    description="Doctor-owned procedure performance and documentation"
                    eyebrow={procedure.procedureNumber}
                    title={procedure.patient.name}
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
                                Read-only procedure context
                            </h2>
                            <p className="mt-1 max-w-3xl text-sm leading-6 text-text-secondary">
                                The selected procedure and completed Nurse
                                readiness are authoritative and cannot be
                                changed here.
                            </p>
                        </div>
                        <StatusBadge tone={isCompleted ? 'success' : 'info'}>
                            {procedure.status.label}
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {procedure.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {procedure.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {procedure.visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(procedure.visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Doctor
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {procedure.doctor.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Selected procedure
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {procedure.selectedProcedure.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {procedure.selectedProcedure.decisionNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Nurse readiness
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {procedure.readiness.readinessNumber}
                                <span className="mt-1 block text-text-secondary">
                                    Responsible Nurse:{' '}
                                    {procedure.readiness.nurse.name}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Current workflow
                            </dt>
                            <dd className="mt-2 text-sm font-medium text-text">
                                {procedure.visit.nextStep}
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <h2 className="text-lg font-semibold text-text">
                        Consultation context
                    </h2>
                    <p className="mt-1 text-sm text-text-secondary tabular-nums">
                        {procedure.clinicalContext.consultationNumber}
                    </p>
                    <dl className="mt-6 grid gap-x-8 gap-y-6 md:grid-cols-2">
                        {[
                            [
                                'Presenting complaint',
                                procedure.clinicalContext.presentingComplaint,
                            ],
                            [
                                'Relevant history',
                                procedure.clinicalContext.relevantHistory,
                            ],
                            [
                                'Current medications',
                                procedure.clinicalContext.currentMedications,
                            ],
                            ['Allergies', procedure.clinicalContext.allergies],
                            [
                                'Examination findings',
                                procedure.clinicalContext.examinationFindings,
                            ],
                            [
                                'Assessment / impression',
                                procedure.clinicalContext.assessmentImpression,
                            ],
                            ['Plan', procedure.clinicalContext.planNotes],
                            [
                                'ASA classification',
                                procedure.clinicalContext.asaClassification,
                            ],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    {label}
                                </dt>
                                <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text">
                                    {valueOrNotRecorded(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 border-b border-border pb-6">
                        <h2 className="text-lg font-semibold text-text">
                            Doctor-owned procedure documentation
                        </h2>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-text-secondary">
                            Findings and outcome are required before completion.
                            Other fields may remain empty when they are not
                            clinically applicable.
                        </p>
                    </div>

                    {procedure.canManage ? (
                        <Form
                            {...update.form(procedure.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ errors, processing }) => (
                                <div className="grid gap-6">
                                    <input
                                        name="expected_lock_version"
                                        type="hidden"
                                        value={procedure.lockVersion}
                                    />
                                    <div className="grid gap-6 lg:grid-cols-2">
                                        <FormField
                                            error={errors.findings}
                                            hint="Required before procedure completion."
                                            id="findings"
                                            label="Findings"
                                            required
                                        >
                                            <textarea
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-36 resize-y py-3',
                                                )}
                                                defaultValue={
                                                    procedure.documentation
                                                        .findings ?? ''
                                                }
                                                id="findings"
                                                maxLength={5000}
                                                name="findings"
                                                rows={6}
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.diagnosis_impression}
                                            id="diagnosis-impression"
                                            label="Diagnosis / impression"
                                        >
                                            <textarea
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-36 resize-y py-3',
                                                )}
                                                defaultValue={
                                                    procedure.documentation
                                                        .diagnosisImpression ??
                                                    ''
                                                }
                                                id="diagnosis-impression"
                                                maxLength={5000}
                                                name="diagnosis_impression"
                                                rows={6}
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.outcome}
                                            hint="Required before procedure completion."
                                            id="procedure-outcome"
                                            label="Outcome"
                                            required
                                        >
                                            <textarea
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-32 resize-y py-3',
                                                )}
                                                defaultValue={
                                                    procedure.documentation
                                                        .outcome ?? ''
                                                }
                                                id="procedure-outcome"
                                                maxLength={5000}
                                                name="outcome"
                                                rows={5}
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.complications}
                                            hint="Leave empty when there are no applicable complications to document."
                                            id="complications"
                                            label="Complications"
                                        >
                                            <textarea
                                                className={cn(
                                                    formControlStyles,
                                                    'min-h-32 resize-y py-3',
                                                )}
                                                defaultValue={
                                                    procedure.documentation
                                                        .complications ?? ''
                                                }
                                                id="complications"
                                                maxLength={5000}
                                                name="complications"
                                                rows={5}
                                            />
                                        </FormField>
                                    </div>

                                    <fieldset className="rounded-control border border-border p-4">
                                        <legend className="px-1 text-sm font-medium text-text">
                                            Specimens
                                        </legend>
                                        <input
                                            name="specimens_taken"
                                            type="hidden"
                                            value="0"
                                        />
                                        <label className="mt-2 flex gap-3 text-sm text-text">
                                            <input
                                                className="mt-1 size-4 accent-brand-primary"
                                                defaultChecked={
                                                    procedure.documentation
                                                        .specimensTaken
                                                }
                                                name="specimens_taken"
                                                type="checkbox"
                                                value="1"
                                            />
                                            <span>
                                                Specimens were taken during this
                                                procedure
                                            </span>
                                        </label>
                                        <div className="mt-4">
                                            <FormField
                                                error={errors.specimen_notes}
                                                hint="Required at completion only when specimens were taken."
                                                id="specimen-notes"
                                                label="Specimen notes"
                                            >
                                                <textarea
                                                    className={cn(
                                                        formControlStyles,
                                                        'min-h-28 resize-y py-3',
                                                    )}
                                                    defaultValue={
                                                        procedure.documentation
                                                            .specimenNotes ?? ''
                                                    }
                                                    id="specimen-notes"
                                                    maxLength={5000}
                                                    name="specimen_notes"
                                                    rows={4}
                                                />
                                            </FormField>
                                        </div>
                                    </fieldset>

                                    <FormField
                                        error={errors.procedure_notes}
                                        id="procedure-notes"
                                        label="Additional procedure notes"
                                    >
                                        <textarea
                                            className={cn(
                                                formControlStyles,
                                                'min-h-32 resize-y py-3',
                                            )}
                                            defaultValue={
                                                procedure.documentation
                                                    .procedureNotes ?? ''
                                            }
                                            id="procedure-notes"
                                            maxLength={5000}
                                            name="procedure_notes"
                                            rows={5}
                                        />
                                    </FormField>

                                    {errors.procedure ? (
                                        <p
                                            className="text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.procedure}
                                        </p>
                                    ) : null}

                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Saving…'
                                                : 'Save procedure documentation'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    ) : (
                        <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2">
                            {[
                                ['Findings', procedure.documentation.findings],
                                [
                                    'Diagnosis / impression',
                                    procedure.documentation.diagnosisImpression,
                                ],
                                ['Outcome', procedure.documentation.outcome],
                                [
                                    'Complications',
                                    procedure.documentation.complications,
                                ],
                                [
                                    'Specimen notes',
                                    procedure.documentation.specimensTaken
                                        ? procedure.documentation.specimenNotes
                                        : 'No specimens recorded',
                                ],
                                [
                                    'Additional procedure notes',
                                    procedure.documentation.procedureNotes,
                                ],
                            ].map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        {label}
                                    </dt>
                                    <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text">
                                        {valueOrNotRecorded(value)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </Panel>

                {procedure.canComplete ? (
                    <Panel className="border-warning-border bg-warning-soft p-5 sm:p-8">
                        <h2 className="text-lg font-semibold text-text">
                            Complete procedure
                        </h2>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-text-secondary">
                            Completion makes this procedure record immutable and
                            hands the patient to Nursing recovery. Recovery is
                            not started by this action.
                        </p>
                        <Form
                            {...complete.form(procedure.id)}
                            options={{ preserveScroll: true }}
                        >
                            {({ errors, processing }) => (
                                <div className="mt-5 grid gap-4">
                                    <input
                                        name="expected_lock_version"
                                        type="hidden"
                                        value={procedure.lockVersion}
                                    />
                                    {Object.values(errors).length > 0 ? (
                                        <div
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {Object.values(errors)[0]}
                                        </div>
                                    ) : null}
                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Completing…'
                                                : 'Complete procedure'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </Panel>
                ) : isCompleted && procedure.recovery ? (
                    <Panel className="border-info-border bg-info-soft p-5 sm:p-8">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold text-info">
                                    Recovery in progress
                                </h2>
                                <p className="mt-2 text-sm text-text-secondary">
                                    {procedure.recovery.recoveryNumber} was
                                    started{' '}
                                    {formatDateTime(
                                        procedure.recovery.startedAt,
                                    )}{' '}
                                    by {procedure.recovery.nurse.name}.
                                </p>
                            </div>
                            <Link
                                className={textLinkStyles}
                                href={recoveryShow(procedure.recovery.id)}
                            >
                                View recovery
                            </Link>
                        </div>
                    </Panel>
                ) : isCompleted ? (
                    <Panel className="border-success-border bg-success-soft p-5 sm:p-8">
                        <h2 className="text-lg font-semibold text-success">
                            Ready for Nursing recovery
                        </h2>
                        <p className="mt-2 text-sm text-text-secondary">
                            Procedure completed{' '}
                            {procedure.completedAt
                                ? formatDateTime(procedure.completedAt)
                                : ''}
                            . No recovery record has been started.
                        </p>
                    </Panel>
                ) : null}
            </PageContainer>
        </>
    );
}

ProcedureShow.layout = [AuthenticatedLayout];
