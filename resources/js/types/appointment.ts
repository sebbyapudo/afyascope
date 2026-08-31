export type AppointmentStatus = {
    value: 'cancelled' | 'no_show' | 'scheduled';
    label: string;
};

export type AppointmentPatient = {
    id: number;
    patientNumber: string;
    name: string;
};

export type AppointmentLinkedVisit = {
    id: number;
    visitNumber: string;
    status: {
        value: 'created';
        label: string;
    };
    nextStep: string;
};

export type AppointmentSummary = {
    id: number;
    appointmentNumber: string;
    scheduledAt: string;
    status: AppointmentStatus;
    isScheduled: boolean;
    linkedVisit: AppointmentLinkedVisit | null;
    patient: AppointmentPatient;
};

export type PatientAppointmentHistoryItem = Omit<
    AppointmentSummary,
    'isScheduled' | 'linkedVisit' | 'patient'
>;

export type AppointmentPage = {
    data: AppointmentSummary[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};

export type AppointmentStatusOption = AppointmentStatus;
