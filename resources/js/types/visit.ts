export type VisitStatus = {
    value: string;
    label: string;
};

export type VisitPatient = {
    id: number;
    patientNumber: string;
    name: string;
};

export type VisitAppointment = {
    id: number;
    appointmentNumber: string;
};

export type VisitSummary = {
    id: number;
    visitNumber: string;
    occurredAt: string;
    status: VisitStatus;
    nextStep: string;
    appointment: VisitAppointment | null;
    patient: VisitPatient;
};

export type PatientVisitHistoryItem = Omit<
    VisitSummary,
    'appointment' | 'patient'
>;

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
