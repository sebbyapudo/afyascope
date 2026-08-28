import { Head, Link } from '@inertiajs/react';
import { StaffForm } from '@/components/staff/staff-form';
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
            <main className="min-h-screen bg-slate-50 p-4 sm:p-8">
                <div className="mx-auto grid max-w-4xl gap-6">
                    <header>
                        <Link
                            className="text-sm font-medium text-sky-800 underline-offset-4 hover:underline"
                            href={index()}
                        >
                            Back to staff accounts
                        </Link>
                        <h1 className="mt-3 text-3xl font-semibold text-slate-900">
                            Edit staff member
                        </h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Update identity, role, or account access. Staff
                            accounts are not routinely deleted.
                        </p>
                    </header>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
                        <StaffForm
                            form={update.form(staffUser.id)}
                            roles={roles}
                            staffUser={staffUser}
                            submitLabel="Save changes"
                        />
                    </section>
                </div>
            </main>
        </>
    );
}
