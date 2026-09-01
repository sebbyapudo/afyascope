export type AuthenticatedUser = {
    id: number;
    name: string;
    email: string;
};

export type StaffRole = {
    slug: string;
    displayName: string;
};

export type Capabilities = {
    viewDashboard: boolean;
    viewUsers: boolean;
    manageUsers: boolean;
    viewRoles: boolean;
    viewAudit: boolean;
    viewPatients: boolean;
    createPatients: boolean;
    updatePatients: boolean;
    viewVisits: boolean;
    createVisits: boolean;
    viewAppointments: boolean;
    createAppointments: boolean;
    updateAppointments: boolean;
    viewBilling: boolean;
    createBilling: boolean;
    viewPayments: boolean;
    createPayments: boolean;
};

export type Auth = {
    user: AuthenticatedUser | null;
    role: StaffRole | null;
    capabilities: Capabilities;
};
