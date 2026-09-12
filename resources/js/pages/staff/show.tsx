import { Head, Link } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { edit, index } from '@/routes/staff';
import type { StaffUserDetail } from '@/types';

type ShowStaffProps = {
    staffUser: StaffUserDetail;
    status?: string | null;
};

function formatDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export default function ShowStaff({ staffUser, status }: ShowStaffProps) {
    return (
        <>
            <Head title={staffUser.name} />
            <PageContainer width="narrow">
                <PageHeader
                    actions={
                        <ActionLink href={edit(staffUser.id)}>
                            Edit staff account
                        </ActionLink>
                    }
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to staff accounts
                        </Link>
                    }
                    description="Account identity, fixed-role assignment, and access status."
                    eyebrow="Staff account"
                    title={staffUser.name}
                />

                {status ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {status}
                    </p>
                ) : null}

                {staffUser.isFinalActiveAdministrator ? (
                    <p
                        className="rounded-control border border-warning-border bg-warning-soft px-4 py-3 text-sm leading-6 text-warning"
                        role="status"
                    >
                        This is the final active Administrator. Add or activate
                        another Administrator before changing this account's
                        role or disabling it.
                    </p>
                ) : null}

                <Panel className="p-5 sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-text">
                                Account details
                            </h2>
                            <p className="mt-1 text-sm text-text-secondary">
                                Historical records retain this staff member's
                                attribution if the role or status changes.
                            </p>
                        </div>
                        <StatusBadge
                            tone={staffUser.isActive ? 'success' : 'neutral'}
                        >
                            {staffUser.isActive ? 'Active' : 'Disabled'}
                        </StatusBadge>
                    </div>

                    <dl className="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Full name
                            </dt>
                            <dd className="mt-1 font-medium text-text">
                                {staffUser.name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Email
                            </dt>
                            <dd className="mt-1 break-all text-text">
                                {staffUser.email}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Fixed role
                            </dt>
                            <dd className="mt-1 font-medium text-text">
                                {staffUser.role.displayName}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Account status
                            </dt>
                            <dd className="mt-1 text-text">
                                {staffUser.isActive ? 'Active' : 'Disabled'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Created
                            </dt>
                            <dd className="mt-1 text-text">
                                {formatDate(staffUser.createdAt)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                Last updated
                            </dt>
                            <dd className="mt-1 text-text">
                                {formatDate(staffUser.updatedAt)}
                            </dd>
                        </div>
                    </dl>
                </Panel>
            </PageContainer>
        </>
    );
}

ShowStaff.layout = [AuthenticatedLayout];
