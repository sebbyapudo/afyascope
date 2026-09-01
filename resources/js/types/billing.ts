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
    financialClearance: {
        id: number;
        clearanceNumber: string;
        grantedAt: string;
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
        id: number;
        billNumber: string;
        type: BillingLabel;
        status: BillingLabel;
        financialClearance: {
            id: number;
            clearanceNumber: string;
            grantedAt: string;
        } | null;
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

export type FinancialClearanceQueueBill = {
    id: number;
    billNumber: string;
    billStatus: BillingLabel;
    totalAmountMinor: number;
    createdAt: string | null;
    payment: {
        paymentNumber: string;
        amountMinor: number;
        recordedAt: string;
        receipt: {
            id: number;
            receiptNumber: string;
        };
    };
    visit: {
        visitNumber: string;
        occurredAt: string;
        nextStep: string;
    };
    patient: {
        patientNumber: string;
        name: string;
    };
};

export type FinancialClearanceQueue = {
    data: FinancialClearanceQueueBill[];
    pagination: ConsultationBillingQueue['pagination'];
};

export type FinancialClearanceDetail = {
    id: number;
    clearanceNumber: string;
    grantedAt: string;
    grantedBy: string;
    bill: {
        id: number;
        billNumber: string;
        status: BillingLabel;
        totalAmountMinor: number;
    };
    payment: FinancialClearanceQueueBill['payment'];
    visit: {
        visitNumber: string;
        occurredAt: string;
        status: BillingLabel;
        nextStep: string;
    };
    patient: FinancialClearanceQueueBill['patient'];
};
