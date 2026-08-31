export type PatientSex = {
    value: string;
    label: string;
};

export type PatientSexOption = PatientSex;

export type PatientSummary = {
    id: number;
    patientNumber: string;
    name: string;
    dateOfBirth: string | null;
    sex: PatientSex | null;
    phone: string | null;
};

export type PatientDetails = PatientSummary & {
    firstName: string;
    middleName: string | null;
    lastName: string;
    email: string | null;
    address: string | null;
    createdAt: string | null;
};

export type PatientHistoryPage<T> = {
    data: T[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        nextPageUrl: string | null;
        pageName: string;
        perPage: number;
        previousPageUrl: string | null;
        to: number | null;
        total: number;
    };
};

export type PatientPage = {
    data: PatientSummary[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};

export type PossiblePatientDuplicate = {
    id: number;
    patientNumber: string;
    name: string;
    dateOfBirth: string | null;
    phone: string | null;
    email: string | null;
};

export type PatientFormData = {
    first_name: string;
    middle_name: string;
    last_name: string;
    date_of_birth: string;
    sex: string;
    phone: string;
    email: string;
    address: string;
};
