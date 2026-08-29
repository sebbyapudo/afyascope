export type AuditValue =
    | string
    | number
    | boolean
    | null
    | AuditValue[]
    | { [key: string]: AuditValue };

export type AuditChange = {
    field: string;
    label: string;
    before: AuditValue;
    after: AuditValue;
};

export type AuditLogEntry = {
    id: number;
    occurredAt: string;
    actor: {
        id: number;
        name: string;
        email: string;
    } | null;
    action: {
        value: string;
        label: string;
    };
    subject: {
        type: string;
        id: number;
        label: string;
    };
    changes: AuditChange[];
};

export type AuditLogPage = {
    data: AuditLogEntry[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};
