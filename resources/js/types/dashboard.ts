export type DashboardKind =
    | 'receptionist'
    | 'accountant'
    | 'doctor'
    | 'nurse'
    | 'administrator'
    | 'management';

export type DashboardMetric = {
    label: string;
    value: number;
    description: string;
};

export type DashboardProjection = {
    kind: DashboardKind;
    eyebrow: string;
    title: string;
    description: string;
    emptyMessage: string;
    period: {
        fromDate: string;
        throughDate: string;
    } | null;
    metrics: DashboardMetric[];
};
