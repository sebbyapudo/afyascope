export type OperationalReportPeriod = {
    fromDate: string;
    throughDate: string;
    timezone: string;
};

export type OperationalReportMetrics = {
    visits: {
        occurred: number;
        active: number;
        completed: number;
    };
    milestones: {
        consultationsStarted: number;
        procedureRequired: number;
        noProcedure: number;
        proceduresCompleted: number;
        recoveriesStarted: number;
        dischargesCompleted: number;
    };
};

export type OperationalStage = {
    key: string;
    label: string;
    count: number;
};

export type OperationalReport = {
    period: OperationalReportPeriod;
    metrics: OperationalReportMetrics;
    stages: OperationalStage[];
};

export type FinancialReportSummary = {
    billedAmountMinor: number;
    paidAmountMinor: number;
    outstandingAmountMinor: number;
    billCount: number;
};

export type FinancialReport = {
    period: OperationalReportPeriod;
    currency: string;
    overall: FinancialReportSummary & {
        paidBillCount: number;
        outstandingBillCount: number;
    };
    consultation: FinancialReportSummary;
    procedure: FinancialReportSummary;
    flow: {
        paymentCount: number;
        receiptCount: number;
        financialClearanceCount: number;
    };
};

export type ClinicalProcedureDistribution = {
    procedureName: string;
    procedureRequiredDecisions: number;
    proceduresCompleted: number;
};

export type ClinicalProcedureReport = {
    period: OperationalReportPeriod;
    consultationDecision: {
        consultationsStarted: number;
        procedureRequired: number;
        noProcedure: number;
    };
    procedure: {
        started: number;
        completed: number;
    };
    preparation: {
        started: number;
        completed: number;
    };
    recovery: {
        started: number;
        completed: number;
        discharged: number;
    };
    escalation: {
        raised: number;
        resolved: number;
    };
    terminalOutcomes: {
        procedurePathVisitsCompleted: number;
        noProcedureVisitsCompleted: number;
    };
    procedureDistribution: ClinicalProcedureDistribution[];
};
