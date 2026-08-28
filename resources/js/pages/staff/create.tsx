import { Head, Link } from '@inertiajs/react';
import { StaffForm } from '@/components/staff/staff-form';
import { index, store } from '@/routes/staff';
import type { RoleOption } from '@/types';

type CreateStaffProps = {
    roles: RoleOption[];
};

export default function CreateStaff({ roles }: CreateStaffProps) {
    return (
        <>
            <Head title="Add staff member" />
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
                            Add staff member
                        </h1>
                        <p className="mt-2 text-sm text-slate-600">
                            Create an account and assign one approved staff
                            role.
                        </p>
                    </header>

                    <section className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
                        <StaffForm
                            form={store.form()}
                            roles={roles}
                            submitLabel="Add staff member"
                        />
                    </section>
                </div>
            </main>
        </>
    );
}
