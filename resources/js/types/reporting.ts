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
