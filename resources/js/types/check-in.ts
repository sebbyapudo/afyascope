import type { VisitStatus } from './visit';

export type CheckInPatient = {
    id: number;
    patientNumber: string;
    name: string;
};

export type CheckInClearance = {
    clearanceNumber: string;
    grantedAt: string;
};

export type CheckInQueueVisit = {
    id: number;
    visitNumber: string;
    occurredAt: string;
    status: VisitStatus;
    nextStep: string;
    clearance: CheckInClearance;
    patient: CheckInPatient;
};

export type CheckInQueue = {
    data: CheckInQueueVisit[];
    pagination: {
        currentPage: number;
        from: number | null;
        lastPage: number;
        to: number | null;
        total: number;
    };
};

export type VisitCheckInDetail = {
    id: number;
    checkInNumber: string;
    checkedInAt: string;
    checkedInBy: string;
    visit: Omit<CheckInQueueVisit, 'clearance' | 'patient'>;
    clearance: CheckInClearance;
    patient: CheckInPatient;
};
