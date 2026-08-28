import { Form, Link } from '@inertiajs/react';
import { index } from '@/routes/staff';
import type { RoleOption, StaffUser } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type StaffFormProps = {
    form: RouteFormDefinition<'post'>;
    roles: RoleOption[];
    staffUser?: StaffUser;
    submitLabel: string;
};

const inputClasses =
    'mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-sky-700 focus:ring-2 focus:ring-sky-700/20';

export function StaffForm({
    form,
    roles,
    staffUser,
    submitLabel,
}: StaffFormProps) {
    return (
        <Form {...form}>
            {({ errors, processing }) => (
                <div className="grid gap-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                className="text-sm font-medium text-slate-800"
                                htmlFor="name"
                            >
                                Full name
                            </label>
                            <input
                                autoComplete="name"
                                className={inputClasses}
                                defaultValue={staffUser?.name}
                                id="name"
                                name="name"
                                required
                                type="text"
                            />
                            {errors.name ? (
                                <p
                                    className="mt-2 text-sm text-red-700"
                                    role="alert"
                                >
                                    {errors.name}
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <label
                                className="text-sm font-medium text-slate-800"
                                htmlFor="email"
                            >
                                Email address
                            </label>
                            <input
                                autoComplete="email"
                                className={inputClasses}
                                defaultValue={staffUser?.email}
                                id="email"
                                name="email"
                                required
                                type="email"
                            />
                            {errors.email ? (
                                <p
                                    className="mt-2 text-sm text-red-700"
                                    role="alert"
                                >
                                    {errors.email}
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <label
                                className="text-sm font-medium text-slate-800"
                                htmlFor="role"
                            >
                                Staff role
                            </label>
                            <select
                                className={inputClasses}
                                defaultValue={
                                    staffUser?.role.slug ?? roles[0]?.value
                                }
                                id="role"
                                name="role"
                                required
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                            {errors.role ? (
                                <p
                                    className="mt-2 text-sm text-red-700"
                                    role="alert"
                                >
                                    {errors.role}
                                </p>
                            ) : null}
                        </div>

                        <div>
                            <label
                                className="text-sm font-medium text-slate-800"
                                htmlFor="is_active"
                            >
                                Account status
                            </label>
                            <select
                                className={inputClasses}
                                defaultValue={
                                    staffUser?.isActive === false ? '0' : '1'
                                }
                                id="is_active"
                                name="is_active"
                            >
                                <option value="1">Active</option>
                                <option value="0">Disabled</option>
                            </select>
                            {errors.is_active ? (
                                <p
                                    className="mt-2 text-sm text-red-700"
                                    role="alert"
                                >
                                    {errors.is_active}
                                </p>
                            ) : null}
                        </div>
                    </div>

                    {!staffUser ? (
                        <p className="rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900">
                            The staff member will receive a secure link to set
                            their password. Administrators do not set staff
                            passwords.
                        </p>
                    ) : null}

                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-6">
                        <Link
                            className="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800"
                            href={index()}
                        >
                            Cancel
                        </Link>
                        <button
                            className="h-11 rounded-xl bg-sky-900 px-5 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            {processing ? 'Saving…' : submitLabel}
                        </button>
                    </div>
                </div>
            )}
        </Form>
    );
}
