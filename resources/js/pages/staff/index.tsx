import { Head, Link } from '@inertiajs/react';
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
            <main className="min-h-screen bg-slate-50 p-4 sm:p-8">
                <div className="mx-auto grid max-w-6xl gap-6">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <Link
                                className="text-sm font-medium text-sky-800 underline-offset-4 hover:underline"
                                href={dashboard()}
                            >
                                Back to dashboard
                            </Link>
                            <h1 className="mt-3 text-3xl font-semibold text-slate-900">
                                Staff accounts
                            </h1>
                            <p className="mt-2 text-sm text-slate-600">
                                Assign one approved role and control account
                                access for each staff member.
                            </p>
                        </div>
                        <Link
                            className="inline-flex h-11 items-center justify-center rounded-xl bg-sky-900 px-5 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800"
                            href={create()}
                        >
                            Add staff member
                        </Link>
                    </header>

                    {status ? (
                        <p
                            className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                            role="status"
                        >
                            {status}
                        </p>
                    ) : null}

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-3xl border-collapse text-left text-sm">
                                <thead className="bg-slate-100 text-xs font-semibold tracking-wide text-slate-600 uppercase">
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
                                <tbody className="divide-y divide-slate-200">
                                    {staffUsers.map((staffUser) => (
                                        <tr key={staffUser.id}>
                                            <td className="px-5 py-4 font-medium text-slate-900">
                                                {staffUser.name}
                                            </td>
                                            <td className="px-5 py-4 text-slate-600">
                                                {staffUser.email}
                                            </td>
                                            <td className="px-5 py-4 text-slate-700">
                                                {staffUser.role.displayName}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={
                                                        staffUser.isActive
                                                            ? 'inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800'
                                                            : 'inline-flex rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700'
                                                    }
                                                >
                                                    {staffUser.isActive
                                                        ? 'Active'
                                                        : 'Disabled'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className="font-semibold text-sky-800 underline-offset-4 hover:underline"
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
                    </section>
                </div>
            </main>
        </>
    );
}
