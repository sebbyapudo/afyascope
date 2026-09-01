export type BillingLabel = {
    value: string;
    label: string;
};

export type ConsultationService = {
    id: number;
    name: string;
    unitPriceMinor: number;
};

export type ConsultationBillingVisit = {
    id: number;
    visitNumber: string;
    occurredAt: string;
    status: BillingLabel;
    patient: {
        patientNumber: string;
        name: string;
    };
};

export type ConsultationBillingQueue = {
    data: ConsultationBillingVisit[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};

export type ConsultationBill = {
    id: number;
    billNumber: string;
    type: BillingLabel;
    status: BillingLabel;
    totalAmountMinor: number;
    createdAt: string | null;
    items: {
        id: number;
        description: string;
        amountMinor: number;
    }[];
    visit: {
        visitNumber: string;
        occurredAt: string;
        status: BillingLabel;
        nextStep: string;
    };
    patient: {
        patientNumber: string;
        name: string;
    };
};
