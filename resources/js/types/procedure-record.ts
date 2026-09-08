export type ProcedureRecordStatus = {
    value: 'completed' | 'in_progress';
    label: string;
};

export type ProcedureQueuePagination = {
    currentPage: number;
    from: number | null;
    lastPage: number;
    pageName: 'in_progress_page' | 'ready_page';
    perPage: number;
    to: number | null;
    total: number;
};

export type ReadyProcedureQueueItem = {
    visit: {
        id: number;
        visitNumber: string;
        occurredAt: string;
        nextStep: string;
    };
    patient: {
        patientNumber: string;
        name: string;
    };
    procedure: {
        name: string;
        decisionNumber: string;
    };
    doctor: {
        name: string;
    };
    readiness: {
        readinessNumber: string;
        nurse: {
            name: string;
        };
        completedAt: string | null;
    };
};

export type ReadyProcedureQueue = {
    data: ReadyProcedureQueueItem[];
    pagination: ProcedureQueuePagination;
};

export type InProgressProcedureQueueItem = {
    id: number;
    procedureNumber: string;
    startedAt: string;
    status: ProcedureRecordStatus;
    visit: {
        visitNumber: string;
        nextStep: string;
    };
    patient: {
        patientNumber: string;
        name: string;
    };
    procedure: {
        name: string;
    };
    doctor: {
        name: string;
    };
    readiness: {
        readinessNumber: string;
        nurse: {
            name: string;
        };
    };
};

export type InProgressProcedureQueue = {
    data: InProgressProcedureQueueItem[];
    pagination: ProcedureQueuePagination;
};

export type ProcedureRecordWorkspace = {
    id: number;
    procedureNumber: string;
    status: ProcedureRecordStatus;
    startedAt: string;
    completedAt: string | null;
    lockVersion: number;
    canManage: boolean;
    canComplete: boolean;
    doctor: {
        name: string;
    };
    patient: {
        patientNumber: string;
        name: string;
        dateOfBirth: string | null;
        sex: string | null;
    };
    visit: {
        visitNumber: string;
        occurredAt: string;
        nextStep: string;
    };
    selectedProcedure: {
        id: number;
        name: string;
        decisionNumber: string;
        clinicalRationale: string | null;
        decidedAt: string;
    };
    readiness: {
        readinessNumber: string;
        status: {
            value: 'ready';
            label: string;
        };
        nurse: {
            name: string;
        };
        completedAt: string | null;
    };
    clinicalContext: {
        consultationNumber: string;
        presentingComplaint: string | null;
        relevantHistory: string | null;
        currentMedications: string | null;
        allergies: string | null;
        examinationFindings: string | null;
        asaClassification: string | null;
        assessmentImpression: string | null;
        planNotes: string | null;
    };
    documentation: {
        findings: string | null;
        diagnosisImpression: string | null;
        specimensTaken: boolean;
        specimenNotes: string | null;
        complications: string | null;
        outcome: string | null;
        procedureNotes: string | null;
    };
    recovery: {
        id: number;
        recoveryNumber: string;
        status: {
            value: 'in_progress';
            label: string;
        };
        startedAt: string;
        nurse: {
            name: string;
        };
    } | null;
};
