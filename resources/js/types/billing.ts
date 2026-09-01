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
    payment: {
        paymentNumber: string;
        amountMinor: number;
        method: BillingLabel;
        recordedAt: string;
        receipt: {
            id: number;
            receiptNumber: string;
        } | null;
    } | null;
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

export type PaymentQueueBill = {
    id: number;
    billNumber: string;
    status: BillingLabel;
    totalAmountMinor: number;
    createdAt: string | null;
    items?: {
        id: number;
        description: string;
        amountMinor: number;
    }[];
    visit: {
        visitNumber: string;
        occurredAt: string;
    };
    patient: {
        patientNumber: string;
        name: string;
    };
};

export type PaymentQueue = {
    data: PaymentQueueBill[];
    pagination: ConsultationBillingQueue['pagination'];
};

export type PaymentMethodOption = BillingLabel;

export type ReceiptDetail = {
    receiptNumber: string;
    issuedAt: string;
    payment: {
        paymentNumber: string;
        amountMinor: number;
        method: BillingLabel;
        recordedAt: string;
        recordedBy: string;
    };
    bill: {
        billNumber: string;
        type: BillingLabel;
        status: BillingLabel;
    };
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
