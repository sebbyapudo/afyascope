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
import { index, update } from '@/routes/clinical/consultations';
import { store as storeProcedureDecision } from '@/routes/clinical/consultations/procedure-decision';
import type {
    AsaClassificationOption,
    ClinicalConsultationWorkspace,
    ProcedureDecisionOutcome,
    ProcedureServiceOption,
} from '@/types';

type ConsultationShowProps = {
    asaClassifications: AsaClassificationOption[];
    consultation: ClinicalConsultationWorkspace;
    procedureServices: ProcedureServiceOption[];
    status?: string | null;
};

const narrativeFields = [
    {
        id: 'presenting-complaint',
        label: 'Presenting complaint / indication',
        name: 'presenting_complaint',
        property: 'presentingComplaint',
    },
    {
        id: 'relevant-history',
        label: 'Relevant history',
        name: 'relevant_history',
        property: 'relevantHistory',
    },
    {
        id: 'current-medications',
        label: 'Current medications',
        name: 'current_medications',
        property: 'currentMedications',
    },
    {
        id: 'allergies',
        label: 'Allergies',
        name: 'allergies',
        property: 'allergies',
    },
    {
        id: 'examination-findings',
        label: 'Examination / clinical findings',
        name: 'examination_findings',
        property: 'examinationFindings',
    },
    {
        id: 'assessment-impression',
        label: 'Assessment / impression',
        name: 'assessment_impression',
        property: 'assessmentImpression',
    },
    {
        id: 'plan-notes',
        label: 'Plan / notes',
        name: 'plan_notes',
        property: 'planNotes',
    },
] as const;

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ConsultationShow({
    asaClassifications,
    consultation,
    procedureServices,
    status,
}: ConsultationShowProps) {
    const { visit } = consultation;
    const [procedureOutcome, setProcedureOutcome] = useState<
        ProcedureDecisionOutcome | ''
    >('');

    return (
        <>
            <Head title={consultation.consultationNumber} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to Doctor queue
                        </Link>
                    }
                    description="In-progress consultation workspace"
                    eyebrow={consultation.consultationNumber}
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
                    <div className="mb-6 flex flex-wrap items-start justify-between gap-4 border-b border-border pb-6">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Consultation in progress
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Started {formatDateTime(consultation.startedAt)}
                                {' by '}
                                {consultation.doctor.name}.
                            </p>
                        </div>
                        <StatusBadge tone="info">
                            {consultation.status.label}
                        </StatusBadge>
                    </div>

                    <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Patient
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {visit.patient.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary tabular-nums">
                                    {visit.patient.patientNumber}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit
                            </dt>
                            <dd className="mt-2 font-semibold text-brand-primary tabular-nums">
                                {visit.visitNumber}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    {formatDateTime(visit.occurredAt)}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Responsible Doctor
                            </dt>
                            <dd className="mt-2 font-medium text-text">
                                {consultation.doctor.name}
                                <span className="mt-1 block text-sm font-normal text-text-secondary">
                                    Assigned when consultation started
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Reception check-in
                            </dt>
                            <dd className="mt-2 text-sm text-text">
                                {formatDateTime(visit.checkIn.checkedInAt)}
                                <span className="mt-1 block text-text-secondary tabular-nums">
                                    {visit.checkIn.checkInNumber}
                                </span>
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Visit workflow
                            </dt>
                            <dd className="mt-2">
                                <StatusBadge tone="success">
                                    {visit.status.label}
                                </StatusBadge>
                                <span className="mt-2 block text-sm text-text-secondary">
                                    {visit.nextStep}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 border-b border-border pb-5">
                        <h2 className="text-lg font-semibold text-text">
                            Clinical assessment
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-text-secondary">
                            {consultation.canManage
                                ? 'Record the current consultation assessment. Empty optional fields are saved as not recorded.'
                                : `Read-only record. This consultation is assigned to ${consultation.doctor.name}.`}
                        </p>
                    </div>

                    {consultation.canManage ? (
                        <Form {...update.form(consultation.id)}>
                            {({ errors, processing }) => (
                                <div className="grid gap-6">
                                    {errors.consultation ? (
                                        <p
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.consultation}
                                        </p>
                                    ) : null}

                                    <div className="grid gap-6 md:grid-cols-2">
                                        {narrativeFields.map((field) => {
                                            const error = errors[field.name];

                                            return (
                                                <FormField
                                                    error={error}
                                                    id={field.id}
                                                    key={field.name}
                                                    label={field.label}
                                                >
                                                    <textarea
                                                        aria-describedby={
                                                            error
                                                                ? `${field.id}-error`
                                                                : undefined
                                                        }
                                                        aria-invalid={Boolean(
                                                            error,
                                                        )}
                                                        className={cn(
                                                            formControlStyles,
                                                            'min-h-32 resize-y py-3',
                                                        )}
                                                        defaultValue={
                                                            consultation
                                                                .assessment[
                                                                field.property
                                                            ] ?? ''
                                                        }
                                                        id={field.id}
                                                        maxLength={5000}
                                                        name={field.name}
                                                        rows={5}
                                                    />
                                                </FormField>
                                            );
                                        })}

                                        <FormField
                                            error={errors.asa_classification}
                                            hint="Optional physical status classification."
                                            id="asa-classification"
                                            label="ASA classification"
                                        >
                                            <select
                                                aria-describedby={
                                                    errors.asa_classification
                                                        ? 'asa-classification-error'
                                                        : 'asa-classification-hint'
                                                }
                                                aria-invalid={Boolean(
                                                    errors.asa_classification,
                                                )}
                                                className={formControlStyles}
                                                defaultValue={
                                                    consultation.assessment
                                                        .asaClassification ?? ''
                                                }
                                                id="asa-classification"
                                                name="asa_classification"
                                            >
                                                <option value="">
                                                    Not recorded
                                                </option>
                                                {asaClassifications.map(
                                                    (classification) => (
                                                        <option
                                                            key={
                                                                classification.value
                                                            }
                                                            value={
                                                                classification.value
                                                            }
                                                        >
                                                            {
                                                                classification.label
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </FormField>
                                    </div>

                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Saving assessment…'
                                                : 'Save assessment'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    ) : (
                        <dl className="grid gap-x-8 gap-y-6 md:grid-cols-2">
                            {narrativeFields.map((field) => (
                                <div key={field.name}>
                                    <dt className="text-sm font-medium text-text">
                                        {field.label}
                                    </dt>
                                    <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                        {consultation.assessment[
                                            field.property
                                        ] ?? 'Not recorded'}
                                    </dd>
                                </div>
                            ))}
                            <div>
                                <dt className="text-sm font-medium text-text">
                                    ASA classification
                                </dt>
                                <dd className="mt-2 text-sm text-text-secondary">
                                    {consultation.assessment.asaClassification
                                        ? `ASA ${consultation.assessment.asaClassification}`
                                        : 'Not recorded'}
                                </dd>
                            </div>
                        </dl>
                    )}
                </Panel>

                <Panel className="p-5 sm:p-8">
                    <div className="mb-6 border-b border-border pb-5">
                        <h2 className="text-lg font-semibold text-text">
                            Procedure decision
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-text-secondary">
                            {consultation.procedureDecision
                                ? 'The authoritative decision is recorded and cannot be changed.'
                                : consultation.canRecordProcedureDecision
                                  ? 'Record whether this consultation requires one primary billable procedure.'
                                  : `Read-only record. Only ${consultation.doctor.name} can record this decision.`}
                        </p>
                    </div>

                    {consultation.procedureDecision ? (
                        <div className="grid gap-6">
                            <dl className="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Decision
                                    </dt>
                                    <dd className="mt-2">
                                        <StatusBadge tone="info">
                                            {
                                                consultation.procedureDecision
                                                    .outcome.label
                                            }
                                        </StatusBadge>
                                        <span className="mt-2 block text-sm text-text-secondary tabular-nums">
                                            {
                                                consultation.procedureDecision
                                                    .decisionNumber
                                            }
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Recorded
                                    </dt>
                                    <dd className="mt-2 text-sm text-text">
                                        {formatDateTime(
                                            consultation.procedureDecision
                                                .decidedAt,
                                        )}
                                        <span className="mt-1 block text-text-secondary">
                                            By {consultation.doctor.name}
                                        </span>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Selected procedure
                                    </dt>
                                    <dd className="mt-2 text-sm text-text">
                                        {consultation.procedureDecision.service
                                            ?.name ?? 'Not applicable'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Billing handoff
                                    </dt>
                                    <dd className="mt-2 text-sm text-text">
                                        {consultation.procedureDecision.handoff
                                            ?.handoffNumber ?? 'Not created'}
                                    </dd>
                                </div>
                                <div className="sm:col-span-2">
                                    <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                        Clinical rationale
                                    </dt>
                                    <dd className="mt-2 text-sm leading-6 whitespace-pre-wrap text-text-secondary">
                                        {consultation.procedureDecision
                                            .clinicalRationale ??
                                            'Not recorded'}
                                    </dd>
                                </div>
                            </dl>

                            <div className="rounded-control border border-info-border bg-info-soft px-4 py-3">
                                <p className="text-sm font-semibold text-text">
                                    Next workflow step
                                </p>
                                <p className="mt-1 text-sm text-text-secondary">
                                    {visit.nextStep}
                                </p>
                            </div>
                        </div>
                    ) : consultation.canRecordProcedureDecision ? (
                        <Form {...storeProcedureDecision.form(consultation.id)}>
                            {({ errors, processing }) => (
                                <div className="grid gap-6">
                                    {errors.consultation ? (
                                        <p
                                            className="rounded-control border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                                            role="alert"
                                        >
                                            {errors.consultation}
                                        </p>
                                    ) : null}

                                    <fieldset>
                                        <legend className="text-sm font-medium text-text">
                                            Decision outcome
                                            <span
                                                aria-hidden="true"
                                                className="text-danger"
                                            >
                                                {' '}
                                                *
                                            </span>
                                        </legend>
                                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                            <label className="flex cursor-pointer gap-3 rounded-control border border-border bg-surface p-4 transition hover:bg-surface-subtle has-checked:border-brand-primary has-checked:ring-2 has-checked:ring-brand-primary/15">
                                                <input
                                                    className="mt-1 size-4 accent-brand-primary"
                                                    name="outcome"
                                                    onChange={() =>
                                                        setProcedureOutcome(
                                                            'procedure_required',
                                                        )
                                                    }
                                                    required
                                                    type="radio"
                                                    value="procedure_required"
                                                />
                                                <span>
                                                    <span className="block text-sm font-semibold text-text">
                                                        Procedure required
                                                    </span>
                                                    <span className="mt-1 block text-xs leading-5 text-text-secondary">
                                                        Select the clinically
                                                        determined procedure and
                                                        send it for billing.
                                                    </span>
                                                </span>
                                            </label>
                                            <label className="flex cursor-pointer gap-3 rounded-control border border-border bg-surface p-4 transition hover:bg-surface-subtle has-checked:border-brand-primary has-checked:ring-2 has-checked:ring-brand-primary/15">
                                                <input
                                                    className="mt-1 size-4 accent-brand-primary"
                                                    name="outcome"
                                                    onChange={() =>
                                                        setProcedureOutcome(
                                                            'no_procedure',
                                                        )
                                                    }
                                                    required
                                                    type="radio"
                                                    value="no_procedure"
                                                />
                                                <span>
                                                    <span className="block text-sm font-semibold text-text">
                                                        No procedure required
                                                    </span>
                                                    <span className="mt-1 block text-xs leading-5 text-text-secondary">
                                                        Record that no billable
                                                        procedure is required
                                                        for this Visit.
                                                    </span>
                                                </span>
                                            </label>
                                        </div>
                                        {errors.outcome ? (
                                            <p
                                                className="mt-2 text-sm text-danger"
                                                role="alert"
                                            >
                                                {errors.outcome}
                                            </p>
                                        ) : null}
                                    </fieldset>

                                    {procedureOutcome ===
                                    'procedure_required' ? (
                                        <FormField
                                            error={
                                                errors.service_catalog_item_id
                                            }
                                            hint="Only active procedure services are available. Prices remain controlled by Billing."
                                            id="procedure-service"
                                            label="Procedure service"
                                            required
                                        >
                                            <select
                                                aria-describedby={
                                                    errors.service_catalog_item_id
                                                        ? 'procedure-service-error'
                                                        : 'procedure-service-hint'
                                                }
                                                aria-invalid={Boolean(
                                                    errors.service_catalog_item_id,
                                                )}
                                                className={formControlStyles}
                                                defaultValue=""
                                                id="procedure-service"
                                                name="service_catalog_item_id"
                                                required
                                            >
                                                <option disabled value="">
                                                    Select procedure
                                                </option>
                                                {procedureServices.map(
                                                    (service) => (
                                                        <option
                                                            key={service.id}
                                                            value={service.id}
                                                        >
                                                            {service.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </FormField>
                                    ) : null}

                                    <FormField
                                        error={errors.clinical_rationale}
                                        hint="Optional concise clinical rationale. This narrative is not copied into the audit log."
                                        id="clinical-rationale"
                                        label="Clinical rationale"
                                    >
                                        <textarea
                                            aria-describedby={
                                                errors.clinical_rationale
                                                    ? 'clinical-rationale-error'
                                                    : 'clinical-rationale-hint'
                                            }
                                            aria-invalid={Boolean(
                                                errors.clinical_rationale,
                                            )}
                                            className={cn(
                                                formControlStyles,
                                                'min-h-28 resize-y py-3',
                                            )}
                                            id="clinical-rationale"
                                            maxLength={2000}
                                            name="clinical_rationale"
                                            rows={4}
                                        />
                                    </FormField>

                                    <div>
                                        <label className="flex max-w-2xl gap-3 rounded-control border border-warning-border bg-warning-soft p-4">
                                            <input
                                                className="mt-1 size-4 accent-brand-primary"
                                                name="confirmed"
                                                required
                                                type="checkbox"
                                                value="1"
                                            />
                                            <span className="text-sm leading-6 text-text">
                                                I confirm this authoritative
                                                procedure decision. It cannot be
                                                changed or deleted in the MVP.
                                            </span>
                                        </label>
                                        {errors.confirmed ? (
                                            <p
                                                className="mt-2 text-sm text-danger"
                                                role="alert"
                                            >
                                                {errors.confirmed}
                                            </p>
                                        ) : null}
                                    </div>

                                    <div>
                                        <Button
                                            disabled={processing}
                                            type="submit"
                                        >
                                            {processing
                                                ? 'Recording decision…'
                                                : 'Record procedure decision'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm leading-6 text-text-secondary">
                            No procedure decision has been recorded.
                        </p>
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}

ConsultationShow.layout = [AuthenticatedLayout];
