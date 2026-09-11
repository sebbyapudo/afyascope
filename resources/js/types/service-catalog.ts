export type ServiceCategory = {
    value: 'consultation' | 'procedure';
    label: string;
};

export type ServiceCategoryOption = ServiceCategory;

export type ServiceCatalogItem = {
    id: number;
    name: string;
    category: ServiceCategory;
    unitPriceMinor: number;
    isActive: boolean;
    isReferenced: boolean;
    usage: {
        billItems: number;
        procedureDecisions: number;
    };
    createdAt: string;
    updatedAt: string;
};

export type ServiceCatalogPage = {
    data: ServiceCatalogItem[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};
