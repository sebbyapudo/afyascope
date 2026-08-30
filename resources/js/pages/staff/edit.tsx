import { Head, Link } from '@inertiajs/react';
import { StaffForm } from '@/components/staff/staff-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index, update } from '@/routes/staff';
import type { RoleOption, StaffUser } from '@/types';

type EditStaffProps = {
    roles: RoleOption[];
    staffUser: StaffUser;
};

export default function EditStaff({ roles, staffUser }: EditStaffProps) {
    return (
        <>
            <Head title={`Edit ${staffUser.name}`} />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to staff accounts
                        </Link>
                    }
                    description="Update identity, role, or account access. Staff accounts are not routinely deleted."
                    title="Edit staff member"
                />

                <Panel className="p-5 sm:p-8">
                    <StaffForm
                        form={update.form(staffUser.id)}
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
