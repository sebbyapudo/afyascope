export type RoleOption = {
    value: string;
    label: string;
};

export type StaffUser = {
    id: number;
    name: string;
    email: string;
    role: {
        slug: string;
        displayName: string;
    };
    isActive: boolean;
};
