export type ReadinessStatus = {
    value: 'in_preparation' | 'ready';
    label: string;
};

export type PreProcedureReadinessQueueItem = {
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
    procedureClearedAt: string;
    readiness: {
        id: number;
        readinessNumber: string;
        status: ReadinessStatus;
        nurse: {
            name: string;
        };
        canManage: boolean;
    } | null;
};

export type PreProcedureReadinessQueue = {
    data: PreProcedureReadinessQueueItem[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        perPage: number;
        to: number | null;
        total: number;
    };
};

export type PreProcedureReadinessWorkspace = {
    id: number;
    readinessNumber: string;
    status: ReadinessStatus;
    startedAt: string;
    completedAt: string | null;
    canManage: boolean;
    canComplete: boolean;
    nurse: {
        name: string;
    };
    checks: {
        consentVerified: boolean;
        patientIdentityVerified: boolean;
        procedureVerified: boolean;
        allergiesReviewed: boolean;
        medicationsReviewed: boolean;
    };
    observations: string | null;
    visit: {
        visitNumber: string;
        occurredAt: string;
        nextStep: string;
    };
    patient: {
        patientNumber: string;
        name: string;
        dateOfBirth: string | null;
        sex: string | null;
    };
    doctor: {
        name: string;
    };
    procedure: {
        name: string | null;
        decisionNumber: string;
        decidedAt: string;
    };
    clinicalContext: {
        allergies: string | null;
        currentMedications: string | null;
        asaClassification: string | null;
    };
};
