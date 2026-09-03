import type { VisitStatus } from './visit';

export type ClinicalPatient = {
    patientNumber: string;
    name: string;
};

export type ClinicalCheckIn = {
    checkInNumber: string;
    checkedInAt: string;
};

export type ClinicalVisitContext = {
    id: number;
    visitNumber: string;
    occurredAt: string;
    status: VisitStatus;
    nextStep: string;
    checkIn: ClinicalCheckIn;
    patient: ClinicalPatient;
};

export type ClinicalConsultation = {
    id: number;
    consultationNumber: string;
    status: {
        value: 'finalized' | 'in_progress';
        label: string;
    };
    startedAt: string;
    canManage: boolean;
    doctor: {
        id: number;
        name: string;
    };
    visit: ClinicalVisitContext;
};

export type ClinicalAssessment = {
    presentingComplaint: string | null;
    relevantHistory: string | null;
    currentMedications: string | null;
    allergies: string | null;
    examinationFindings: string | null;
    asaClassification: string | null;
    assessmentImpression: string | null;
    planNotes: string | null;
};

export type ClinicalConsultationWorkspace = ClinicalConsultation & {
    assessment: ClinicalAssessment;
    canRecordProcedureDecision: boolean;
    procedureDecision: ClinicalProcedureDecision | null;
};

export type ProcedureDecisionOutcome = 'no_procedure' | 'procedure_required';

export type ClinicalProcedureDecision = {
    decisionNumber: string;
    outcome: {
        value: ProcedureDecisionOutcome;
        label: string;
    };
    clinicalRationale: string | null;
    decidedAt: string;
    service: ProcedureServiceOption | null;
    handoff: {
        handoffNumber: string;
    } | null;
};

export type ProcedureServiceOption = {
    id: number;
    name: string;
};

export type AsaClassificationOption = {
    value: string;
    label: string;
};

export type ClinicalPagination = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    pageName: 'in_progress_page' | 'ready_page';
    perPage: number;
    to: number | null;
    total: number;
};

export type ClinicalQueue = {
    data: ClinicalVisitContext[];
    pagination: ClinicalPagination;
};

export type InProgressConsultationQueue = {
    data: ClinicalConsultation[];
    pagination: ClinicalPagination;
};
