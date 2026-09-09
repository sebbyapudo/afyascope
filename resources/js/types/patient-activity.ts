export type PatientActivityDestination = {
    type:
        | 'appointment'
        | 'bill'
        | 'check_in'
        | 'clearance'
        | 'consultation'
        | 'preparation'
        | 'procedure'
        | 'receipt'
        | 'recovery'
        | 'visit';
    id: number;
};

export type PatientActivityItem = {
    patient: {
        patientNumber: string;
        name: string;
    };
    visit: {
        visitNumber: string;
        currentStage: string;
    };
    activity: {
        type: string;
        label: string;
        reference: string;
        occurredAt: string;
    };
    destination: PatientActivityDestination;
};

export type PatientActivityPage = {
    data: PatientActivityItem[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        pageName: 'activity_page';
        perPage: number;
        to: number | null;
        total: number;
    };
};

export type PatientActivityOption = {
    value: string;
    label: string;
};
