export type AppointmentStatus = {
    value: 'cancelled' | 'no_show' | 'scheduled';
    label: string;
};

export type AppointmentPatient = {
    id: number;
    patientNumber: string;
    name: string;
};

export type AppointmentSummary = {
    id: number;
    appointmentNumber: string;
    scheduledAt: string;
    status: AppointmentStatus;
    isScheduled: boolean;
    patient: AppointmentPatient;
};

export type UpcomingAppointment = Omit<
    AppointmentSummary,
    'isScheduled' | 'patient'
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
