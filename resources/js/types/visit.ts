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

export type VisitConsultationBill = {
    billNumber: string;
    status: VisitStatus;
    totalAmountMinor: number;
};

export type VisitSummary = {
    id: number;
    visitNumber: string;
    occurredAt: string;
    status: VisitStatus;
    nextStep: string;
    consultationBill: VisitConsultationBill | null;
    appointment: VisitAppointment | null;
    patient: VisitPatient;
};

export type PatientVisitHistoryItem = Omit<
    VisitSummary,
    'appointment' | 'consultationBill' | 'patient'
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
