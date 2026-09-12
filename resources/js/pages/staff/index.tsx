import { Form, Head, Link } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { StatusBadge } from '@/components/ui/status-badge';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { create, edit, index, show } from '@/routes/staff';
import type { RoleOption, StaffUserPage } from '@/types';

type StaffIndexProps = {
    filters: {
        q: string;
        role: string | null;
        status: string | null;
    };
    roles: RoleOption[];
    staffUsers: StaffUserPage;
    status?: string | null;
};

export default function StaffIndex({
    filters,
    roles,
    staffUsers,
    status,
}: StaffIndexProps) {
    const { data, pagination } = staffUsers;

    return (
        <>
            <Head title="Staff" />
            <PageContainer width="wide">
                <PageHeader
                    actions={
                        <ActionLink href={create()}>
                            Add staff member
                        </ActionLink>
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

                <Panel className="p-5 sm:p-6">
                    <Form {...index.form()}>
                        {({ processing }) => (
                            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_14rem_12rem_auto] lg:items-end">
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="staff-q"
                                    >
                                        Search staff
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.q}
                                        id="staff-q"
                                        name="q"
                                        placeholder="Name or email"
                                        type="search"
                                    />
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="staff-role"
                                    >
                                        Role
                                    </label>
                                    <select
                                        className={formControlStyles}
                                        defaultValue={filters.role ?? ''}
                                        id="staff-role"
                                        name="role"
                                    >
                                        <option value="">All roles</option>
                                        {roles.map((role) => (
                                            <option
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="staff-status"
                                    >
                                        Account status
                                    </label>
                                    <select
                                        className={formControlStyles}
                                        defaultValue={filters.status ?? ''}
                                        id="staff-status"
                                        name="status"
                                    >
                                        <option value="">All statuses</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">
                                            Disabled
                                        </option>
                                    </select>
                                </div>
                                <div className="flex gap-2">
                                    <Button disabled={processing} type="submit">
                                        Filter
                                    </Button>
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear
                                    </ActionLink>
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            action={
                                filters.q || filters.role || filters.status ? (
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear filters
                                    </ActionLink>
                                ) : (
                                    <ActionLink href={create()}>
                                        Add staff member
                                    </ActionLink>
                                )
                            }
                            description="No staff accounts match the current view."
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
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.map((staffUser) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={staffUser.id}
                                        >
                                            <td className="px-5 py-4 font-medium text-text">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={show(staffUser.id)}
                                                >
                                                    {staffUser.name}
                                                </Link>
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
                                            <td className="px-5 py-4">
                                                <div className="flex justify-end gap-3">
                                                    <Link
                                                        className={
                                                            textLinkStyles
                                                        }
                                                        href={show(
                                                            staffUser.id,
                                                        )}
                                                    >
                                                        View
                                                    </Link>
                                                    <Link
                                                        className={
                                                            textLinkStyles
                                                        }
                                                        href={edit(
                                                            staffUser.id,
                                                        )}
                                                    >
                                                        Edit
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {pagination.from}–{pagination.to} of{' '}
                                {pagination.total}
                            </p>
                            <nav
                                aria-label="Staff account pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                ...filters,
                                                page:
                                                    pagination.currentPage - 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {pagination.currentPage <
                                pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                ...filters,
                                                page:
                                                    pagination.currentPage + 1,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Next
                                    </ActionLink>
                                ) : null}
                            </nav>
                        </footer>
                    ) : null}
                </Panel>
            </PageContainer>
        </>
    );
}

StaffIndex.layout = [AuthenticatedLayout];
