import { Head, Link } from '@inertiajs/react';
import { ActionLink, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import { dashboard } from '@/routes';
import { create, edit } from '@/routes/staff';
import type { StaffUser } from '@/types';

type StaffIndexProps = {
    staffUsers: StaffUser[];
    status?: string | null;
};

export default function StaffIndex({ staffUsers, status }: StaffIndexProps) {
    return (
        <>
            <Head title="Staff" />
            <PageContainer>
                <PageHeader
                    actions={
                        <ActionLink href={create()}>
                            Add staff member
                        </ActionLink>
                    }
                    backLink={
                        <Link className={textLinkStyles} href={dashboard()}>
                            Back to dashboard
                        </Link>
                    }
                    description="Assign one approved role and control account access for each staff member."
                    title="Staff accounts"
                />

                {status ? (
                    <p
                        className="rounded-control border border-success-border bg-success-soft px-4 py-3 text-sm text-success"
                        role="status"
                    >
                        {status}
                    </p>
                ) : null}

                <Panel className="overflow-hidden">
                    {staffUsers.length === 0 ? (
                        <EmptyState
                            action={
                                <ActionLink href={create()}>
                                    Add staff member
                                </ActionLink>
                            }
                            description="Create a staff account and assign one of the approved roles."
                            title="No staff accounts found"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-3xl border-collapse text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Name
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Email
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Role
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Status
                                        </th>
                                        <th
                                            className="px-5 py-4 text-right"
                                            scope="col"
                                        >
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {staffUsers.map((staffUser) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={staffUser.id}
                                        >
                                            <td className="px-5 py-4 font-medium text-text">
                                                {staffUser.name}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {staffUser.email}
                                            </td>
                                            <td className="px-5 py-4 text-text">
                                                {staffUser.role.displayName}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge
                                                    tone={
                                                        staffUser.isActive
                                                            ? 'success'
                                                            : 'neutral'
                                                    }
                                                >
                                                    {staffUser.isActive
                                                        ? 'Active'
                                                        : 'Disabled'}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={edit(staffUser.id)}
                                                >
                                                    Edit
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}
