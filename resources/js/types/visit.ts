export type VisitStatus = {
    value: string;
    label: string;
};

export type VisitPatient = {
    id: number;
    patientNumber: string;
    name: string;
};

export type VisitSummary = {
    id: number;
    visitNumber: string;
    occurredAt: string;
    status: VisitStatus;
    nextStep: string;
    patient: VisitPatient;
};

export type RecentVisit = Omit<VisitSummary, 'patient'>;

export type VisitPage = {
    data: VisitSummary[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};
