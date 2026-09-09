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
        value: 'in_progress';
        label: string;
    };
    startedAt: string;
    completedAt: string | null;
    canManage: boolean;
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
    observations: RecoveryObservation[];
};
