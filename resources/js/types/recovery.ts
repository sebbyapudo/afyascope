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
        perPage: number;
        to: number | null;
        total: number;
    };
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
};
