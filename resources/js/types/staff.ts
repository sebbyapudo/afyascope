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

export type StaffUserDetail = StaffUser & {
    createdAt: string;
    updatedAt: string;
    isFinalActiveAdministrator: boolean;
};

export type StaffUserPage = {
    data: StaffUser[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};
