export type AuditValue = string | number | boolean | null;

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
        isActive: boolean;
        roleAtEvent: null;
    } | null;
    action: {
        value: string;
        label: string;
    };
    subject: {
        type: string;
        reference: string | null;
        internalId: number;
    };
    changes: AuditChange[];
};

export type AuditMetadataItem = {
    field: string;
    label: string;
    value: AuditValue;
};

export type AuditLogDetail = AuditLogEntry & {
    metadata: AuditMetadataItem[];
};

export type AuditFilterOption = {
    value: string;
    label: string;
};

export type AuditLogFilters = {
    q: string;
    event: string | null;
    actor: string;
    subjectType: string | null;
    subjectReference: string;
    dateFrom: string | null;
    dateTo: string | null;
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
