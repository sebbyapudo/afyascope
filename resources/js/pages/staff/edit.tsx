import { Head, Link } from '@inertiajs/react';
import { StaffForm } from '@/components/staff/staff-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { show, update } from '@/routes/staff';
import type { RoleOption, StaffUserDetail } from '@/types';

type EditStaffProps = {
    roles: RoleOption[];
    staffUser: StaffUserDetail;
};

export default function EditStaff({ roles, staffUser }: EditStaffProps) {
    return (
        <>
            <Head title={`Edit ${staffUser.name}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link
                            className={textLinkStyles}
                            href={show(staffUser.id)}
                        >
                            Back to staff account
                        </Link>
                    }
                    description="Update identity, role, or account access. Staff accounts are not routinely deleted."
                    title="Edit staff member"
                />

                {staffUser.isFinalActiveAdministrator ? (
                    <p
                        className="rounded-control border border-warning-border bg-warning-soft px-4 py-3 text-sm leading-6 text-warning"
                        role="status"
                    >
                        This is the final active Administrator. Its role and
                        active status are protected until another active
                        Administrator exists.
                    </p>
                ) : null}

                <Panel className="p-5 sm:p-8">
                    <StaffForm
                        form={update.form(staffUser.id)}
                        protectAdministratorAccess={
                            staffUser.isFinalActiveAdministrator
                        }
                        roles={roles}
                        staffUser={staffUser}
                        submitLabel="Save changes"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}

EditStaff.layout = [AuthenticatedLayout];
