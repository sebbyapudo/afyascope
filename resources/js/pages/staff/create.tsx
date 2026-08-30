import { Head, Link } from '@inertiajs/react';
import { StaffForm } from '@/components/staff/staff-form';
import { textLinkStyles } from '@/components/ui/button';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import { index, store } from '@/routes/staff';
import type { RoleOption } from '@/types';

type CreateStaffProps = {
    roles: RoleOption[];
};

export default function CreateStaff({ roles }: CreateStaffProps) {
    return (
        <>
            <Head title="Add staff member" />
            <PageContainer width="narrow">
                <PageHeader
                    backLink={
                        <Link className={textLinkStyles} href={index()}>
                            Back to staff accounts
                        </Link>
                    }
                    description="Create an account and assign one approved staff role."
                    title="Add staff member"
                />

                <Panel className="p-5 sm:p-8">
                    <StaffForm
                        form={store.form()}
                        roles={roles}
                        submitLabel="Add staff member"
                    />
                </Panel>
            </PageContainer>
        </>
    );
}
