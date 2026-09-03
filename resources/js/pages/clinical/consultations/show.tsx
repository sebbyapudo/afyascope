import { Form, Head, Link } from '@inertiajs/react';
import { Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/clinical/consultations';
import type {
    AsaClassificationOption,
    ClinicalConsultationWorkspace,
} from '@/types';

type ConsultationShowProps = {
    asaClassifications: AsaClassificationOption[];
    consultation: ClinicalConsultationWorkspace;
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
    status,
}: ConsultationShowProps) {
    const { visit } = consultation;

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
            </PageContainer>
        </>
    );
}

ConsultationShow.layout = [AuthenticatedLayout];
