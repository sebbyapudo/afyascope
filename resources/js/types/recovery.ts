export type RecoveryProcedureContext = {
    id: number;
    procedureNumber: string;
    completedAt: string | null;
    patient: {
        patientNumber: string;
        name: string;
    };
    visit: {
        visitNumber: string;
        occurredAt: string;
        nextStep: string;
    };
    procedure: {
        name: string;
    };
    doctor: {
        name: string;
    };
};

export type RecoveryQueue = {
    data: RecoveryProcedureContext[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        pageName: 'awaiting_page' | 'active_page';
        perPage: number;
        to: number | null;
        total: number;
    };
};

export type ActiveRecoveryQueueItem = {
    id: number;
    recoveryNumber: string;
    startedAt: string;
    patient: { patientNumber: string; name: string };
    visit: { visitNumber: string; nextStep: string };
    procedure: { procedureNumber: string; name: string };
    doctor: { name: string };
};

export type ActiveRecoveryQueue = Omit<RecoveryQueue, 'data'> & {
    data: ActiveRecoveryQueueItem[];
};

export type RecoveryObservation = {
    id: number;
    generalRecoveryStatus: string;
    painScore: number | null;
    nausea: boolean;
    vomiting: boolean;
    systolicBloodPressure: number | null;
    diastolicBloodPressure: number | null;
    pulseRate: number | null;
    respiratoryRate: number | null;
    oxygenSaturation: number | null;
    supplementalOxygen: boolean;
    nursingNote: string | null;
    recordedAt: string;
    recordedBy: { name: string };
};

export type RecoveryWorkspace = {
    id: number;
    recoveryNumber: string;
    status: {
        value: 'in_progress' | 'ready_for_discharge' | 'completed';
        label: string;
    };
    startedAt: string;
    completedAt: string | null;
    canManage: boolean;
    isResponsibleNurse: boolean;
    canAssessReadiness: boolean;
    canDischarge: boolean;
    canResolveEscalation: boolean;
    nurse: {
        name: string;
    };
    patient: {
        patientNumber: string;
        name: string;
    };
    visit: {
        visitNumber: string;
        occurredAt: string;
        nextStep: string;
    };
    procedure: {
        id: number;
        procedureNumber: string;
        name: string;
        completedAt: string | null;
    };
    doctor: {
        name: string;
    };
    readinessAssessment: {
        criteriaMet: boolean;
        clinicalConcernRequiresEscalation: boolean;
        assessmentNote: string | null;
        assessedAt: string;
        assessedBy: { name: string };
    } | null;
    escalations: RecoveryEscalation[];
    discharge: RecoveryDischarge | null;
    observations: RecoveryObservation[];
};

export type RecoveryDischarge = {
    dischargeNumber: string;
    conditionSummary: string;
    accompanimentStatus: {
        value: 'accompanied' | 'not_accompanied' | 'not_applicable';
        label: string;
    };
    disposition: {
        value: 'home' | 'other_facility' | 'other';
        label: string;
    };
    nursingNote: string | null;
    generalCareInstructions: string;
    activityDrivingInstructions: string;
    dietFluidsInstructions: string;
    medicationInstructions: string | null;
    warningSignsInstructions: string;
    followUpInstructions: string | null;
    dischargedAt: string;
    dischargedBy: { name: string };
};

export type RecoveryEscalation = {
    id: number;
    reason: string;
    status: {
        value: 'open' | 'resolved';
        label: string;
    };
    escalatedAt: string;
    escalatedBy: { name: string };
    resolution: {
        value: 'continue_monitoring' | 'clinically_cleared';
        label: string;
    } | null;
    resolutionNote: string | null;
    resolvedAt: string | null;
    resolvedBy: { name: string } | null;
};

export type RecoveryEscalationQueueItem = {
    id: number;
    reason: string;
    escalatedAt: string;
    escalatedBy: { name: string };
    recovery: {
        id: number;
        recoveryNumber: string;
        nurse: { name: string };
    };
    patient: { patientNumber: string; name: string };
    visit: { visitNumber: string };
    procedure: { procedureNumber: string; name: string };
};
